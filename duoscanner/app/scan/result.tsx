import React, { useEffect, useState, useMemo } from 'react';
import { View, Text, ScrollView, Pressable, Alert } from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { MaterialIcons } from '@expo/vector-icons';
import { Header } from '@/components/ui/Header';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { BubbleGrid } from '@/components/ui/BubbleGrid';
import { Badge } from '@/components/ui/Badge';
import { CorrectionSummaryCard } from '@/components/scan/CorrectionSummaryCard';
import { useExamStore } from '@/store/exam-store';
import { useScanStore } from '@/store/scan-store';
import { useSyncStore } from '@/store/sync-store';
import { useConfigStore } from '@/store/config-store';
import {
  applyCorrection,
  buildCorrectionFromCache,
  type CorrectionResult,
  type CorrectionInput,
} from '@/lib/correction-engine';
import {
  mapQuestionValuesToPrintedPositions,
  mapVisualAnswersToOriginalOptions,
} from '@/lib/answer-mapping';
import { getResolvedConfig, getDataStrategy } from '@/lib/config-resolver';
import { addToSyncQueue, getSyncQueueCount } from '@/db/database';
import { processQueue, syncSingleScan } from '@/services/sync-manager';
import { colors } from '@/theme/colors';
import { fonts } from '@/theme/typography';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

export default function ResultScreen() {
  const { examId, localId } = useLocalSearchParams<{ examId: string; localId: string }>();
  const insets = useSafeAreaInsets();
  const cachedExam = useExamStore((s) => s.getCachedExam(Number(examId)));
  const { currentScan, finalizeScan } = useScanStore();
  const { isOnline } = useSyncStore();
  const config = getResolvedConfig();

  const [correctionResult, setCorrectionResult] = useState<CorrectionResult | null>(null);
  const [syncing, setSyncing] = useState(false);
  const [showDetails, setShowDetails] = useState(false);
  const [filterMode, setFilterMode] = useState<'all' | 'correct' | 'wrong' | 'review'>('all');

  const questions = cachedExam?.data.questions || [];
  const copy = cachedExam?.data.copies.find((c) => c.id === currentScan?.copyId);

  useEffect(() => {
    if (!currentScan?.confirmedAnswers && !currentScan?.detectedAnswers) return;

    const answers = currentScan.confirmedAnswers || currentScan.detectedAnswers || {};
    const confidences = currentScan.questionConfidences || {};

    let input: CorrectionInput | null = null;

    // Try cache-based correction first (for preloaded and hybrid modes)
    if (questions.length > 0) {
      // OMR returns the visual bubble index. Cached answer keys use the
      // original option index, so apply the copy's immutable options_map only
      // for the provisional on-device score. The visual values remain intact
      // for server synchronization and authoritative grading.
      const originalOptionAnswers = mapVisualAnswersToOriginalOptions(
        answers,
        copy?.options_map
      );
      input = buildCorrectionFromCache(
        { questions: questions.map((q: any) => ({
          id: q.id,
          number: q.question_number ?? q.id,
          correct_option: q.correct_option ?? 0,
          points: q.points ?? 1,
        })) },
        originalOptionAnswers as Record<number, number | null>,
        confidences as Record<number, number>
      );
    }

    if (input) {
      const result = applyCorrection(input);
      setCorrectionResult(result);
    }
  }, []);

  // Filtered details
  const filteredDetails = useMemo(() => {
    if (!correctionResult) return [];
    switch (filterMode) {
      case 'correct': return correctionResult.details.filter((d) => d.isCorrect);
      case 'wrong': return correctionResult.details.filter((d) => !d.isCorrect && !d.isBlank);
      case 'review': return correctionResult.details.filter((d) => d.needsReview);
      default: return correctionResult.details;
    }
  }, [correctionResult, filterMode]);

  const handleSaveAndNext = async () => {
    if (!localId || !currentScan) return;

    const finalScan = buildFinalScan();
    await saveScanToDb(finalScan);
    await addToSyncQueue(localId);
    useSyncStore.getState().updatePendingCount(await getSyncQueueCount());
    finalizeScan(finalScan);

    router.replace('/(tabs)');
  };

  const handleSyncNow = async () => {
    if (!localId || !currentScan) return;

    const finalScan = buildFinalScan();
    await saveScanToDb(finalScan);
    await addToSyncQueue(localId);
    useSyncStore.getState().updatePendingCount(await getSyncQueueCount());
    finalizeScan(finalScan);

    setSyncing(true);
    try {
      const success = await syncSingleScan(localId);
      if (success) {
        Alert.alert('Sincronizado', 'Scan enviado com sucesso ao servidor.');
      } else {
        Alert.alert('Pendente', 'Scan salvo na fila. Será sincronizado quando possível.');
      }
    } catch {
      Alert.alert('Pendente', 'Scan salvo na fila. Será sincronizado quando possível.');
    } finally {
      setSyncing(false);
    }

    router.replace('/(tabs)');
  };

  const mapToPrintedPositions = <T extends number | null>(values: Record<string, T>) => {
    // Cached scans use database question IDs; offline QR validation binds data
    // to printed positions. Convert only when the cached copy is available.
    const qrVersion = Number(currentScan?.qrVersion ?? currentScan?.qrPayload?.v ?? 0);
    return mapQuestionValuesToPrintedPositions(values, copy?.questions_map, qrVersion);
  };

  const buildFinalScan = () => {
    // Manual review is authoritative. Keeping the reviewed values in both
    // fields also makes a later offline upload deterministic.
    const detectedAnswers = currentScan?.confirmedAnswers || currentScan?.detectedAnswers || {};
    const questionConfidences = currentScan?.questionConfidences || {};

    return {
      ...currentScan as any,
      localId,
      examId: Number(examId),
      score: correctionResult?.score ?? 0,
      totalPoints: correctionResult?.totalPoints ?? 0,
      status: 'confirmed' as const,
      createdAt: currentScan?.createdAt || new Date().toISOString(),
      syncedAt: null,
      serverScanId: null,
      confirmedAnswers: detectedAnswers,
      imageUri: currentScan?.imageUri || '',
      copyId: currentScan?.copyId || null,
      validationHash: currentScan?.validationHash || '',
      studentId: currentScan?.studentId || null,
      studentName: currentScan?.studentName || null,
      confidenceScore: currentScan?.confidenceScore || 0,
      detectedAnswers,
      printedAnswers: mapToPrintedPositions(detectedAnswers),
      printedConfidences: mapToPrintedPositions(questionConfidences),
    };
  };

  const saveScanToDb = async (scan: any) => {
    const { saveScan: dbSaveScan } = await import('@/db/database');
    await dbSaveScan({
      local_id: scan.localId,
      exam_id: scan.examId,
      copy_id: scan.copyId,
      student_id: scan.studentId,
      status: scan.status,
      image_uri: scan.imageUri,
      detected_answers: JSON.stringify(scan.detectedAnswers),
      confirmed_answers: JSON.stringify(scan.confirmedAnswers),
      confidence_score: scan.confidenceScore,
      answer_sheet_type: config.answerSheetType,
      scan_mode: config.scanMode,
      idempotency_key: `${scan.examId}-${scan.copyId}-${scan.localId}`,
      session_id: scan.sessionId || scan.localId,
      page_index: scan.pageIndex || 1,
      total_pages: scan.pageTotal || 1,
      created_at: scan.createdAt,
      synced_at: null,
      server_scan_id: null,
      payload_json: JSON.stringify(scan),
    });
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Header title="Resultado do Scan" showBack />

      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 200 }}>
        {/* Offline Badge */}
        {!isOnline && (
          <View style={{ alignItems: 'center', marginBottom: 12 }}>
            <View style={styles.offlineBadge}>
              <MaterialIcons name="sync-disabled" size={14} color={colors.amber} />
              <Text style={styles.offlineBadgeText}>Modo offline · Sincronização pendente</Text>
            </View>
          </View>
        )}

        {/* Config info */}
        <View style={styles.configRow}>
          <View style={styles.configPill}>
            <MaterialIcons name="description" size={12} color={colors.textMuted} />
            <Text style={styles.configPillText}>
              {config.answerSheetType === 'essential' ? 'Essential' : 'Detalhado'}
            </Text>
          </View>
          <View style={styles.configPill}>
            <MaterialIcons name="sync-alt" size={12} color={colors.textMuted} />
            <Text style={styles.configPillText}>
              {config.scanMode === 'hybrid' ? 'Híbrido' : config.scanMode === 'preloaded' ? 'Pré-carregado' : 'Via QR'}
            </Text>
          </View>
        </View>

        {/* Correction Summary Card */}
        {correctionResult && (
          <CorrectionSummaryCard
            result={correctionResult}
            studentName={currentScan?.studentName || undefined}
          />
        )}

        {/* Student info */}
        <Card style={styles.studentCard}>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
            <View style={styles.avatarCircle}>
              <MaterialIcons name="person" size={24} color={colors.primary} />
            </View>
            <View style={{ flex: 1 }}>
              <Text style={styles.studentName}>
                {currentScan?.studentName || 'Aluno não identificado'}
              </Text>
              <Text style={styles.studentMeta}>
                Prova #{examId} · Versão #{currentScan?.copyId || '-'}
              </Text>
            </View>
          </View>
        </Card>

        {/* Questions detail */}
        <View style={styles.detailsHeader}>
          <Pressable
            onPress={() => setShowDetails(!showDetails)}
            style={styles.detailsToggle}
          >
            <Text style={styles.detailsTitle}>Detalhes por Questão</Text>
            <MaterialIcons
              name={showDetails ? 'expand-less' : 'expand-more'}
              size={24}
              color={colors.textPrimary}
            />
          </Pressable>

          {showDetails && correctionResult && (
            <ScrollView
              horizontal
              showsHorizontalScrollIndicator={false}
              style={{ marginTop: 8 }}
              contentContainerStyle={{ gap: 6 }}
            >
              {(['all', 'correct', 'wrong', 'review'] as const).map((mode) => {
                const count = mode === 'all'
                  ? correctionResult.details.length
                  : mode === 'correct'
                  ? correctionResult.correctCount
                  : mode === 'wrong'
                  ? correctionResult.incorrectCount
                  : correctionResult.reviewCount;
                const active = filterMode === mode;
                const label = mode === 'all' ? 'Todas' : mode === 'correct' ? 'Acertos' : mode === 'wrong' ? 'Erros' : 'Revisar';

                return (
                  <Pressable
                    key={mode}
                    onPress={() => setFilterMode(mode)}
                    style={[styles.filterChip, active && styles.filterChipActive]}
                  >
                    <Text style={[styles.filterChipText, active && styles.filterChipTextActive]}>
                      {label} ({count})
                    </Text>
                  </Pressable>
                );
              })}
            </ScrollView>
          )}
        </View>

        {showDetails && (
          <View style={{ gap: 6, marginTop: 8 }}>
            {filteredDetails.map((d) => (
              <BubbleGrid
                key={d.questionNumber}
                questionNumber={d.questionNumber}
                optionCount={5}
                selectedOption={d.detectedOption}
                correctOption={d.correctOption}
                confidence={d.confidence}
                showCorrect={true}
              />
            ))}
            {filteredDetails.length === 0 && (
              <View style={styles.emptyFilter}>
                <Text style={styles.emptyFilterText}>Nenhuma questão nesta categoria</Text>
              </View>
            )}
          </View>
        )}
      </ScrollView>

      {/* Bottom Actions */}
      <View style={[styles.bottomActions, { paddingBottom: insets.bottom + 16 }]}>
        <Button
          title="Salvar e Próximo"
          onPress={handleSaveAndNext}
          size="lg"
          icon={<MaterialIcons name="save" size={18} color={colors.white} />}
        />
        <Button
          title={syncing ? 'Sincronizando...' : 'Sincronizar Agora'}
          onPress={handleSyncNow}
          variant="outline"
          loading={syncing}
          disabled={syncing || !isOnline}
          icon={<MaterialIcons name="sync" size={18} color={isOnline ? colors.primary : colors.gray} />}
        />
      </View>
    </View>
  );
}

const styles = {
  offlineBadge: {
    flexDirection: 'row' as const,
    alignItems: 'center' as const,
    gap: 6,
    backgroundColor: colors.amberLight,
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: colors.amber + '40',
  },
  offlineBadgeText: {
    fontSize: 11,
    fontFamily: fonts.bold,
    color: '#92400e',
  },
  configRow: {
    flexDirection: 'row' as const,
    gap: 8,
    marginBottom: 16,
    justifyContent: 'center' as const,
  },
  configPill: {
    flexDirection: 'row' as const,
    alignItems: 'center' as const,
    gap: 4,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 999,
    backgroundColor: colors.grayLight,
  },
  configPillText: {
    fontSize: 11,
    fontFamily: fonts.bold,
    color: colors.textMuted,
  },
  studentCard: {
    marginTop: 16,
  },
  avatarCircle: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: colors.primaryLight,
    alignItems: 'center' as const,
    justifyContent: 'center' as const,
  },
  studentName: {
    fontSize: 16,
    fontFamily: fonts.bold,
    color: colors.textPrimary,
  },
  studentMeta: {
    fontSize: 12,
    fontFamily: fonts.medium,
    color: colors.textMuted,
    marginTop: 2,
  },
  detailsHeader: {
    marginTop: 24,
    marginBottom: 4,
  },
  detailsToggle: {
    flexDirection: 'row' as const,
    alignItems: 'center' as const,
    justifyContent: 'space-between' as const,
  },
  detailsTitle: {
    fontSize: 16,
    fontFamily: fonts.extraBold,
    color: colors.textPrimary,
  },
  filterChip: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 999,
    backgroundColor: colors.grayLight,
    borderWidth: 1,
    borderColor: colors.border,
  },
  filterChipActive: {
    backgroundColor: colors.primaryLight,
    borderColor: colors.primary,
  },
  filterChipText: {
    fontSize: 11,
    fontFamily: fonts.bold,
    color: colors.textMuted,
  },
  filterChipTextActive: {
    color: colors.primary,
  },
  emptyFilter: {
    padding: 24,
    alignItems: 'center' as const,
  },
  emptyFilterText: {
    fontSize: 13,
    fontFamily: fonts.medium,
    color: colors.textMuted,
  },
  bottomActions: {
    position: 'absolute' as const,
    bottom: 0,
    left: 0,
    right: 0,
    backgroundColor: colors.white + 'ee',
    borderTopWidth: 2,
    borderTopColor: colors.border,
    padding: 16,
    gap: 10,
  },
};
