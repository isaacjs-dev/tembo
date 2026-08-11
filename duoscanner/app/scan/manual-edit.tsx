import React, { useState } from 'react';
import { View, Text, ScrollView, Pressable, Alert } from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { MaterialIcons } from '@expo/vector-icons';
import { Header } from '@/components/ui/Header';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { useExamStore } from '@/store/exam-store';
import { useScanStore } from '@/store/scan-store';
import { createManualOMRResult } from '@/lib/omr-processor';
import { colors } from '@/theme/colors';
import { fonts } from '@/theme/typography';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

const LETTERS = ['A', 'B', 'C', 'D', 'E'];

export default function ManualEditScreen() {
  const { examId, localId, imageUri } = useLocalSearchParams<{ examId: string; localId: string; imageUri: string }>();
  const insets = useSafeAreaInsets();
  const cachedExam = useExamStore((s) => s.getCachedExam(Number(examId)));
  const { currentScan, updateCurrentScan } = useScanStore();

  const copy = cachedExam?.data.copies.find((c) => c.id === currentScan?.copyId);
  const questions = cachedExam?.data.questions || [];
  const offlineQuestionIds = Array.from(
    { length: Math.max(0, (currentScan?.qEnd ?? 0) - (currentScan?.qStart ?? 1) + 1) },
    (_, index) => (currentScan?.qStart ?? 1) + index
  );
  const orderedQuestionIds = (copy?.questions_map && copy.questions_map.length > 0)
    ? copy.questions_map
    : questions.length > 0 ? questions.map((q) => q.id) : offlineQuestionIds;

  const getOptionCount = (qId: number) => {
    const encoded = currentScan?.qrOptionCounts?.[orderedQuestionIds.indexOf(qId)];
    if (encoded !== undefined) return Number(encoded);
    return questions.find((q) => q.id === qId)?.option_count || 5;
  };

  const [answers, setAnswers] = useState<Record<string, number | null>>(
    currentScan?.detectedAnswers || {}
  );
  const [changes, setChanges] = useState<Set<string>>(new Set());
  const objectiveQuestionIds = orderedQuestionIds.filter((questionId) => getOptionCount(questionId) > 0);
  const requiredReviewIds = currentScan?.omrEvidence?.action === 'rescan'
    ? objectiveQuestionIds.map(String)
    : Object.entries(currentScan?.omrEvidence?.questions ?? {})
        .filter(([, evidence]) => evidence.action === 'review')
        .map(([questionId]) => questionId);

  const handleSelect = (qId: string, option: number) => {
    const current = answers[qId];
    const newAnswer = current === option ? null : option;
    setAnswers((prev) => ({ ...prev, [qId]: newAnswer }));
    setChanges((prev) => new Set(prev).add(qId));
  };

  const handleSave = () => {
    const unresolved = requiredReviewIds.filter((questionId) => !changes.has(questionId));
    if (unresolved.length > 0) {
      Alert.alert(
        'Revisão incompleta',
        `Confirme manualmente as questões indicadas. Ainda faltam ${unresolved.length}.`,
      );
      return;
    }
    const manual = createManualOMRResult(answers, objectiveQuestionIds);
    const isFullManualReview = currentScan?.omrEvidence?.action === 'rescan';
    const questionConfidences = Object.fromEntries(objectiveQuestionIds.map((questionId) => {
      const key = String(questionId);
      return [key, changes.has(key)
        ? manual.confidences[key] ?? 0
        : currentScan?.questionConfidences?.[key] ?? 0];
    }));
    const confidenceValues = Object.values(questionConfidences);
    const overallConfidence = confidenceValues.length
      ? confidenceValues.reduce((sum, value) => sum + value, 0) / confidenceValues.length
      : 0;
    updateCurrentScan({
      detectedAnswers: answers,
      confirmedAnswers: answers,
      questionConfidences,
      confidenceScore: overallConfidence,
      omrEvidence: {
        pipelineVersion: 'mobile-v2',
        processingPath: isFullManualReview ? 'manual' : 'hybrid',
        action: 'accept',
        reasons: ['manual_confirmation'],
        imageQuality: currentScan?.omrEvidence?.imageQuality ?? null,
        geometry: currentScan?.omrEvidence?.geometry ?? {
          fiducialCount: 0,
          fiducialConfidence: 0,
          reprojectionError: null,
          orientationDegrees: null,
          scaleRatio: null,
        },
        questions: Object.fromEntries(objectiveQuestionIds.map((questionId) => {
          const key = String(questionId);
          const original = currentScan?.omrEvidence?.questions?.[key];
          const manuallyResolved = changes.has(key);
          return [key, {
            action: 'accept',
            reasons: manuallyResolved ? ['manual_confirmation'] : original?.reasons ?? [],
            fillRatios: original?.fillRatios ?? [],
            confidence: questionConfidences[key] ?? 0,
            roi: original?.roi ?? { x: 0, y: 0, w: 0, h: 0 },
          }];
        })),
      },
    });
    // Go to student selection before showing result
    router.push({
      pathname: '/scan/review-data',
      params: { examId, localId, imageUri: currentScan?.imageUri || '' },
    });
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Header title="Editar Manualmente" showBack />

      {/* Student Info */}
      <View style={{ paddingHorizontal: 16, paddingVertical: 12, borderBottomWidth: 2, borderBottomColor: colors.border, backgroundColor: colors.white, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
          <View style={{ width: 44, height: 44, borderRadius: 22, backgroundColor: colors.primaryLight, alignItems: 'center', justifyContent: 'center' }}>
            <MaterialIcons name="person" size={24} color={colors.primary} />
          </View>
          <View>
            <Text style={{ fontSize: 10, fontFamily: fonts.bold, color: colors.textSecondary, textTransform: 'uppercase' }}>
              Estudante
            </Text>
            <Text style={{ fontSize: 15, fontFamily: fonts.bold, color: colors.textPrimary }}>
              {currentScan?.studentName || 'Nao identificado'}
            </Text>
          </View>
        </View>
        <Badge
          label={`${Math.round((currentScan?.confidenceScore || 0) * 100)}% IA Conf.`}
          variant={(currentScan?.confidenceScore || 0) >= 0.7 ? 'success' : 'warning'}
        />
      </View>

      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 120, gap: 12 }}>
        <Text style={{ fontSize: 12, fontFamily: fonts.bold, color: colors.textSecondary, textTransform: 'uppercase', marginBottom: 4 }}>
          Gabarito Detectado
        </Text>

        {orderedQuestionIds.map((qId, index) => {
          const optionCount = getOptionCount(qId);
          if (optionCount === 0) return null;
          const qIdStr = String(qId);
          const isChanged = changes.has(qIdStr);
          const detected = currentScan?.detectedAnswers?.[qIdStr];
          const current = answers[qIdStr];

          return (
            <View
              key={qId}
              style={{
                backgroundColor: colors.white,
                borderWidth: 2,
                borderColor: isChanged ? colors.primary + '40' : colors.border,
                borderRadius: 12,
                padding: 16,
              }}
            >
              <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                <Text style={{ fontSize: 16, fontFamily: fonts.bold, color: colors.textPrimary }}>
                  Questao {String(index + 1).padStart(2, '0')}
                </Text>
                {isChanged ? (
                  <Badge label="Alterado" variant="info" />
                ) : current !== null ? (
                  <Badge label="Original" variant="neutral" />
                ) : (
                  <Badge label="Revisao Necessaria" variant="warning" />
                )}
              </View>

              <View style={{ flexDirection: 'row', gap: 8 }}>
                {Array.from({ length: optionCount }, (_, i) => (
                  <Pressable
                    key={i}
                    onPress={() => handleSelect(qIdStr, i)}
                    style={{
                      width: 44,
                      height: 44,
                      borderRadius: 22,
                      borderWidth: 2,
                      borderColor: current === i ? colors.primary : colors.border,
                      backgroundColor: current === i ? colors.primaryLight : colors.white,
                      alignItems: 'center',
                      justifyContent: 'center',
                    }}
                  >
                    <Text style={{ fontSize: 15, fontFamily: fonts.bold, color: current === i ? colors.primary : colors.textSecondary }}>
                      {LETTERS[i]}
                    </Text>
                  </Pressable>
                ))}
              </View>

              {isChanged && detected !== undefined && detected !== null && (
                <Text style={{ fontSize: 11, fontFamily: fonts.medium, color: colors.textSecondary, marginTop: 8 }}>
                  Detectado originalmente: {LETTERS[detected]}
                </Text>
              )}

              {current === null && !isChanged && (
                <Text style={{ fontSize: 11, fontFamily: fonts.medium, color: colors.amber, marginTop: 8 }}>
                  Marcacao ambigua detectada pelo scanner.
                </Text>
              )}
            </View>
          );
        })}
      </ScrollView>

      {/* Bottom Actions */}
      <View style={{ padding: 16, paddingBottom: insets.bottom + 16, backgroundColor: colors.white, borderTopWidth: 2, borderTopColor: colors.border, gap: 8 }}>
        <Button
          title="Salvar Alteracoes"
          onPress={handleSave}
          size="lg"
          icon={<MaterialIcons name="save" size={18} color={colors.white} />}
        />
        <Pressable onPress={() => router.back()} style={{ alignItems: 'center', paddingVertical: 12 }}>
          <Text style={{ fontSize: 15, fontFamily: fonts.bold, color: colors.textSecondary }}>Cancelar</Text>
        </Pressable>
      </View>
    </View>
  );
}
