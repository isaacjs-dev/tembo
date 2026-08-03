import React, { useState, useEffect } from 'react';
import { View, Text, ScrollView, Image, Alert, ActivityIndicator, LayoutChangeEvent } from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { MaterialIcons } from '@expo/vector-icons';
import { Header } from '@/components/ui/Header';
import { Button } from '@/components/ui/Button';
import { BubbleGrid } from '@/components/ui/BubbleGrid';
import { Badge } from '@/components/ui/Badge';
import { useExamStore } from '@/store/exam-store';
import { useScanStore } from '@/store/scan-store';
import { processOMR, createManualOMRResult, CONFIDENCE_AUTO_ACCEPT, CONFIDENCE_QUESTION_REVIEW } from '@/lib/omr-processor';
import type { BubbleMarkType } from '@/types/scan';
import { colors } from '@/theme/colors';
import { fonts } from '@/theme/typography';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import Svg, { Circle, Line, Polygon } from 'react-native-svg';

export default function ReviewMarksScreen() {
  const { examId, localId, imageUri } = useLocalSearchParams<{
    examId: string;
    localId: string;
    imageUri: string;
  }>();
  const insets = useSafeAreaInsets();
  const cachedExam = useExamStore((s) => s.getCachedExam(Number(examId)));
  const { currentScan, updateCurrentScan } = useScanStore();
  const [processing, setProcessing] = useState(true);
  const [processingStep, setProcessingStep] = useState('Detectando marcadores...');
  const [detectedAnswers, setDetectedAnswers] = useState<Record<string, number | null>>({});
  const [confidences, setConfidences] = useState<Record<string, number>>({});
  const [markTypes, setMarkTypes] = useState<Record<string, BubbleMarkType>>({});
  const [multipleMarks, setMultipleMarks] = useState<Record<string, number[]>>({});
  const [fiducialCorners, setFiducialCorners] = useState<{ x: number; y: number }[]>([]);
  const [omrMeta, setOmrMeta] = useState<{ usedHomography: boolean; fiducialCount: number; flagged: string[]; needsRescan: boolean } | null>(null);

  // States to keep track of image layout box for scaling SVGs
  const [imgLayout, setImgLayout] = useState({ width: 0, height: 0 });

  const copy = cachedExam?.data.copies.find(
    (c) => c.id === currentScan?.copyId
  );
  const questions = cachedExam?.data.questions || [];
  const offlineQuestionIds = Array.from(
    { length: Math.max(0, (currentScan?.qEnd ?? 0) - (currentScan?.qStart ?? 1) + 1) },
    (_, index) => (currentScan?.qStart ?? 1) + index
  );
  // Use questions_map from copy if available, otherwise use question IDs in order
  const orderedQuestionIds = (copy?.questions_map && copy.questions_map.length > 0)
    ? copy.questions_map
    : questions.length > 0 ? questions.map((q) => q.id) : offlineQuestionIds;

  const getOptionCount = (qId: number) => {
    const encoded = currentScan?.qrOptionCounts?.[orderedQuestionIds.indexOf(qId)];
    if (encoded !== undefined) return Number(encoded);
    return questions.find((q) => q.id === qId)?.option_count || 5;
  };

  useEffect(() => {
    runOMR();
  }, []);

  const runOMR = async () => {
    if (!imageUri || !orderedQuestionIds.length) {
      setProcessing(false);
      return;
    }

    try {
      const optionCounts: Record<string, number> = {};
      orderedQuestionIds.forEach((qId) => {
        optionCounts[String(qId)] = getOptionCount(qId);
      });

      setProcessingStep('Corrigindo perspectiva...');
      const result = await processOMR(imageUri, {
        questionIds: orderedQuestionIds,
        optionCounts,
        layoutVersion: currentScan?.layoutVersion ?? 0,
        qrGeometry: currentScan?.qrGeometry,
      });

      setDetectedAnswers(result.answers);
      setConfidences(result.confidences);
      setMarkTypes(result.markTypes);
      setMultipleMarks(result.multipleMarks);
      if (result.fiducialCorners) {
        setFiducialCorners(result.fiducialCorners);
      }
      setOmrMeta({
        usedHomography: result.usedHomography,
        fiducialCount: result.fiducialCount,
        flagged: result.flaggedForReview,
        needsRescan: result.needsRescan,
      });
      updateCurrentScan({
        detectedAnswers: result.answers,
        questionConfidences: result.confidences,
        confidenceScore: result.overallConfidence,
      });

      if (result.needsRescan) {
        Alert.alert(
          'Qualidade baixa',
          'A confiança geral da leitura está abaixo do mínimo. Recomendamos repetir a captura com mais luz e câmera paralela ao cartão.',
          [
            { text: 'Repetir captura', style: 'destructive', onPress: handleRetake },
            { text: 'Continuar assim', style: 'cancel' },
          ]
        );
      }
    } catch (error: any) {
      console.warn('OMR processing failed:', error?.message || error);
      const emptyAnswers: Record<string, number | null> = {};
      const emptyConfidences: Record<string, number> = {};
      orderedQuestionIds.forEach((qId) => {
        emptyAnswers[String(qId)] = null;
        emptyConfidences[String(qId)] = 0;
      });
      setDetectedAnswers(emptyAnswers);
      setConfidences(emptyConfidences);
      Alert.alert(
        'Detecção automática falhou',
        'Não foi possível detectar as marcações automaticamente. Use "Editar Manualmente" para preencher as respostas.',
      );
    } finally {
      setProcessing(false);
    }
  };

  const lowConfidenceQuestions = Object.entries(confidences)
    .filter(([_, conf]) => conf < CONFIDENCE_QUESTION_REVIEW)
    .map(([qId]) => qId);

  const multipleMarkQuestions = Object.keys(multipleMarks);


  const handleConfirm = () => {
    updateCurrentScan({ confirmedAnswers: detectedAnswers });
    // Go to student selection before showing result
    router.push({
      pathname: '/scan/review-data',
      params: { examId, localId, imageUri },
    });
  };

  const handleManualEdit = () => {
    router.push({
      pathname: '/scan/manual-edit',
      params: { examId, localId, imageUri },
    });
  };

  const handleRetake = () => {
    router.replace({
      pathname: '/scan/camera',
      params: { examId },
    });
  };

  const onImageLayout = (event: LayoutChangeEvent) => {
    const { width, height } = event.nativeEvent.layout;
    setImgLayout({ width, height });
  };

  if (processing) {
    return (
      <View style={{ flex: 1, backgroundColor: colors.background, alignItems: 'center', justifyContent: 'center' }}>
        <ActivityIndicator size="large" color={colors.primary} />
        <Text style={{ fontSize: 16, fontFamily: fonts.bold, color: colors.textPrimary, marginTop: 16 }}>
          Processando OMR...
        </Text>
        <Text style={{ fontSize: 13, fontFamily: fonts.medium, color: colors.textSecondary, marginTop: 8 }}>
          {processingStep}
        </Text>
      </View>
    );
  }

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Header title="Revisar Marcacoes" showBack />

      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 200 }}>
        {/* Image Preview */}
        <View style={{ marginBottom: 16 }}>
          <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
            <Text style={{ fontSize: 12, fontFamily: fonts.bold, color: colors.textSecondary, textTransform: 'uppercase' }}>
              Miniatura do Gabarito
            </Text>
            <Badge label="PDF Carregado" variant="success" />
          </View>
          {/* OMR Method badge */}
          {omrMeta && (
            <View style={{ flexDirection: 'row', gap: 8, marginBottom: 12, flexWrap: 'wrap' }}>
              <Badge
                label={omrMeta.usedHomography ? `✓ ${omrMeta.fiducialCount} fiduciais` : '⚠ Sem homografia'}
                variant={omrMeta.usedHomography ? 'success' : 'warning'}
              />
              {omrMeta.needsRescan && (
                <Badge label="Re-scan sugerido" variant="danger" />
              )}
            </View>
          )}

          <View
            style={{ height: 150, borderRadius: 12, overflow: 'hidden', borderWidth: 2, borderColor: colors.border, position: 'relative' }}
            onLayout={onImageLayout}
          >
            <Image source={{ uri: imageUri }} style={{ width: '100%', height: '100%' }} resizeMode="cover" />

            {/* SVG Overlay for markers */}
            {fiducialCorners.length === 4 && imgLayout.width > 0 && (
              <View style={{ position: 'absolute', top: 0, left: 0, right: 0, bottom: 0 }} pointerEvents="none">
                <Svg width="100%" height="100%" viewBox="0 0 1200 1600" preserveAspectRatio="none">
                  <Polygon
                    points={fiducialCorners.map(c => `${c.x},${c.y}`).join(' ')}
                    fill="rgba(34, 197, 94, 0.15)"
                    stroke="rgb(34, 197, 94)"
                    strokeWidth="4"
                    strokeDasharray="10, 10"
                  />
                  {fiducialCorners.map((corn, i) => (
                    <React.Fragment key={`corn-${i}`}>
                      <Circle cx={corn.x} cy={corn.y} r="20" fill="none" stroke={colors.danger} strokeWidth="6" />
                      <Line x1={corn.x - 30} y1={corn.y} x2={corn.x + 30} y2={corn.y} stroke={colors.danger} strokeWidth="4" />
                      <Line x1={corn.x} y1={corn.y - 30} x2={corn.x} y2={corn.y + 30} stroke={colors.danger} strokeWidth="4" />
                    </React.Fragment>
                  ))}

                  {/* For bubble bounding rects, we calculate based on multiple marks and confidence thresholds later, but for now we only need fiducials bounds to prove OpenCV.js integration was successful on RN. Full layout coordinates matching will be mapped by template. */}
                </Svg>
              </View>
            )}
          </View>
        </View>


        {/* Detected Answers */}
        <Text style={{ fontSize: 12, fontFamily: fonts.bold, color: colors.textSecondary, textTransform: 'uppercase', marginBottom: 12 }}>
          Respostas Detectadas
        </Text>

        {/* Confidence Legend */}
        <View style={{ flexDirection: 'row', gap: 12, marginBottom: 12 }}>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 4 }}>
            <View style={{ width: 8, height: 8, borderRadius: 4, backgroundColor: colors.primary }} />
            <Text style={{ fontSize: 11, fontFamily: fonts.medium, color: colors.textSecondary }}>Alta</Text>
          </View>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 4 }}>
            <View style={{ width: 8, height: 8, borderRadius: 4, backgroundColor: colors.amber }} />
            <Text style={{ fontSize: 11, fontFamily: fonts.medium, color: colors.textSecondary }}>Media</Text>
          </View>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 4 }}>
            <View style={{ width: 8, height: 8, borderRadius: 4, backgroundColor: colors.danger }} />
            <Text style={{ fontSize: 11, fontFamily: fonts.medium, color: colors.textSecondary }}>Baixa</Text>
          </View>
        </View>

        {/* Answer Grid */}
        <View style={{ gap: 8 }}>
          {orderedQuestionIds.slice(0, 9).map((qId, index) => {
            const optionCount = getOptionCount(qId);
            if (optionCount === 0) return null;

            return (
              <BubbleGrid
                key={qId}
                questionNumber={index + 1}
                optionCount={optionCount}
                selectedOption={detectedAnswers[String(qId)] ?? null}
                confidence={confidences[String(qId)] || 0}
              />
            );
          })}
        </View>

        {/* Multiple marks warning */}
        {multipleMarkQuestions.length > 0 && (
          <View style={{ marginTop: 8, backgroundColor: '#FEF3C7', borderWidth: 1, borderColor: colors.amber + '60', borderRadius: 12, padding: 12, flexDirection: 'row', gap: 8 }}>
            <MaterialIcons name="error-outline" size={20} color={colors.amber} />
            <Text style={{ flex: 1, fontSize: 12, fontFamily: fonts.medium, color: '#92400e', lineHeight: 18 }}>
              {multipleMarkQuestions.length} questão(ões) com múltiplas marcações — serão contadas como erradas.
            </Text>
          </View>
        )}

        {/* Low Confidence Warning */}
        {lowConfidenceQuestions.length > 0 && (
          <View style={{ marginTop: 8, backgroundColor: colors.amberLight, borderWidth: 1, borderColor: colors.amber + '40', borderRadius: 12, padding: 12, flexDirection: 'row', gap: 8 }}>
            <MaterialIcons name="warning" size={20} color={colors.amber} />
            <Text style={{ flex: 1, fontSize: 12, fontFamily: fonts.medium, color: '#92400e', lineHeight: 18 }}>
              Baixa confiança em {lowConfidenceQuestions.length} questão(ões). Verifique manualmente.
            </Text>
          </View>
        )}
      </ScrollView>

      {/* Bottom Actions */}
      <View style={{ position: 'absolute', bottom: 0, left: 0, right: 0, backgroundColor: colors.white + 'ee', borderTopWidth: 2, borderTopColor: colors.border, padding: 16, paddingBottom: insets.bottom + 16, gap: 10 }}>
        <Button
          title="Repetir Captura"
          onPress={handleRetake}
          variant="secondary"
          icon={<MaterialIcons name="replay" size={18} color={colors.textPrimary} />}
        />
        <Button
          title="Editar Manualmente"
          onPress={handleManualEdit}
          variant="outline"
          icon={<MaterialIcons name="edit" size={18} color={colors.primary} />}
        />
        <Button
          title="Confirmar e Enviar"
          onPress={handleConfirm}
          size="lg"
          icon={<MaterialIcons name="send" size={18} color={colors.white} />}
        />
      </View>
    </View>
  );
}
