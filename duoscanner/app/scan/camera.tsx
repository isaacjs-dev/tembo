import React, { useState, useRef, useEffect, useCallback } from 'react';
import { View, Text, Pressable, Alert, StyleSheet, ActivityIndicator } from 'react-native';
import { CameraView, useCameraPermissions } from 'expo-camera';
import { router, useLocalSearchParams } from 'expo-router';
import { MaterialIcons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useExamStore } from '@/store/exam-store';
import { useScanStore } from '@/store/scan-store';
import { useConfigStore } from '@/store/config-store';
import { useSyncStore } from '@/store/sync-store';
import { examService } from '@/services/exams';
import { explainExamDownloadError } from '@/services/exam-download-error';
import { parseQRCode, validateQRAgainstExam, canCaptureOffline } from '@/lib/qr-parser';
import { validateQRPayload } from '@/lib/qr-validator';
import { getResolvedConfig, getDataStrategy } from '@/lib/config-resolver';
import { evaluatePreCapture, shouldAutoCapture, type PreCaptureValidation } from '@/lib/capture-engine';
import { saveScanImage, generateLocalId } from '@/lib/image-utils';
import { individualizedStudent } from '@/lib/exam-copy';
import { CaptureOverlay } from '@/components/scan/CaptureOverlay';
import { ScanModeIndicator } from '@/components/scan/ScanModeIndicator';
import { GuidanceOverlay } from '@/components/scan/GuidanceOverlay';
import { Button } from '@/components/ui/Button';
import { colors } from '@/theme/colors';
import { fonts } from '@/theme/typography';
import type { ExamDownload } from '@/types/exam';
import type { QRPayload } from '@/types/scan';

type CameraState =
  | 'waiting_permission'
  | 'scanning_qr'
  | 'loading_exam'
  | 'qr_error'
  | 'version_conflict'
  | 'ready_to_capture'
  | 'auto_capturing'
  | 'capturing'
  | 'processing';

export default function CameraScreen() {
  const { examId: paramExamId } = useLocalSearchParams<{ examId?: string }>();
  const insets = useSafeAreaInsets();
  const cameraRef = useRef<CameraView>(null);
  const [permission, requestPermission] = useCameraPermissions();

  // State
  const [cameraState, setCameraState] = useState<CameraState>('scanning_qr');
  const [flash, setFlash] = useState(false);
  const [qrData, setQrData] = useState<QRPayload | null>(null);
  const [examTitle, setExamTitle] = useState('');
  const [validation, setValidation] = useState<PreCaptureValidation | null>(null);
  const [versionWarning, setVersionWarning] = useState<string | null>(null);
  const autoCaptureTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Stores
  const { getCachedExam, cacheExam, isExamCached } = useExamStore();
  const { startScan, updateCurrentScan } = useScanStore();
  const { effective, configVersion } = useConfigStore();
  const { isOnline } = useSyncStore();

  // Resolved config
  const config = getResolvedConfig();
  const hasCache = qrData ? isExamCached(qrData.e) : false;
  const dataStrategy = getDataStrategy(config.scanMode, hasCache, isOnline);
  const resolvedExamId = qrData?.e || (paramExamId ? Number(paramExamId) : null);

  const bindIndividualizedStudent = (data: ExamDownload, copyId: number) => {
    const student = individualizedStudent(data, copyId);
    if (student) updateCurrentScan(student);
  };

  useEffect(() => {
    if (!permission?.granted) {
      setCameraState('waiting_permission');
      requestPermission();
    }
  }, []);

  useEffect(() => {
    if (permission?.granted && cameraState === 'waiting_permission') {
      setCameraState('scanning_qr');
    }
  }, [permission]);

  // Cleanup auto-capture timer
  useEffect(() => {
    return () => {
      if (autoCaptureTimer.current) clearTimeout(autoCaptureTimer.current);
    };
  }, []);

  // Simulate frame analysis for quality (in production, this comes from native module)
  const updateQualitySimulation = useCallback(() => {
    // This would normally come from the native OMR frame analysis
    // For now, simulate with reasonable defaults when exam is ready
    if (cameraState === 'ready_to_capture' || cameraState === 'auto_capturing') {
      const simulated = evaluatePreCapture({
        edgeCount: 4,
        markerPositions: [
          { x: 0, y: 0 },
          { x: 1, y: 0 },
          { x: 0, y: 1 },
          { x: 1, y: 1 },
        ],
        laplacianVariance: 180,
        tiltAngle: 2,
        meanBrightness: 155,
      });
      setValidation(simulated);

      if (shouldAutoCapture(simulated) && cameraState === 'ready_to_capture') {
        setCameraState('auto_capturing');
        autoCaptureTimer.current = setTimeout(() => {
          capturePhoto();
        }, 1500);
      }
    }
  }, [cameraState]);

  useEffect(() => {
    const interval = setInterval(updateQualitySimulation, 800);
    return () => clearInterval(interval);
  }, [updateQualitySimulation]);

  const loadExamData = async (qr: QRPayload) => {
    const eid = qr.e;
    setCameraState('loading_exam');
    const strategy = getDataStrategy(config.scanMode, isExamCached(eid), isOnline);

    // Strategy-based data acquisition
    if (strategy === 'use_cache' && isExamCached(eid)) {
      const cached = getCachedExam(eid);
      if (cached) {
        const copies = cached.data.copies.map((c) => ({
          id: c.id,
          validation_hash: c.validation_hash,
        }));
        const qrValidation = validateQRAgainstExam(qr, eid, copies);
        if (!qrValidation.valid) {
          if (isOnline) {
            try {
              const refreshed = await examService.download(eid);
              await cacheExam(eid, refreshed);
              const refreshedCopies = refreshed.copies.map((c) => ({ id: c.id, validation_hash: c.validation_hash }));
              if (validateQRAgainstExam(qr, eid, refreshedCopies).valid) {
                bindIndividualizedStudent(refreshed, qr.c);
                setExamTitle(refreshed.exam.title);
                setCameraState('ready_to_capture');
                return;
              }
            } catch (error) {
              const info = explainExamDownloadError(error);
              Alert.alert(info.title, info.message);
              setCameraState('qr_error');
              return;
            }
          }
          Alert.alert('QR Inválido', qrValidation.error || 'Versão da prova não encontrada.');
          setCameraState('qr_error');
          return;
        }
        bindIndividualizedStudent(cached.data, qr.c);
        setExamTitle(cached.data.exam.title);
        setCameraState('ready_to_capture');
        return;
      }
    }

    if (strategy === 'download_on_demand') {
      try {
        const data = await examService.download(eid);
        await cacheExam(eid, data);

        const copies = data.copies.map((c) => ({
          id: c.id,
          validation_hash: c.validation_hash,
        }));
        const qrValidation = validateQRAgainstExam(qr, eid, copies);
        if (!qrValidation.valid) {
          Alert.alert('QR Inválido', qrValidation.error || 'Versão da prova não encontrada.');
          setCameraState('qr_error');
          return;
        }

        bindIndividualizedStudent(data, qr.c);
        setExamTitle(data.exam.title);
        setCameraState('ready_to_capture');
        return;
      } catch (error: any) {
        const info = explainExamDownloadError(error);
        const msg = error.response?.data?.error || 'Não foi possível carregar a prova.';
        // If hybrid mode, fallback to QR data
        if (config.scanMode === 'hybrid' && canCaptureOffline(qr)) {
          setExamTitle(`Prova #${eid} (captura offline)`);
          setVersionWarning('Cartão aceito offline. A correção será concluída ao sincronizar.');
          setCameraState('ready_to_capture');
          return;
        }
        Alert.alert(info.title, info.message);
        setCameraState('qr_error');
        return;
      }
    }

    if (strategy === 'use_qr_fallback') {
      if (canCaptureOffline(qr)) {
        setExamTitle(`Prova #${eid} (via QR)`);
        setVersionWarning('Cartão aceito offline. A autenticidade e a correção serão concluídas ao sincronizar.');
        setCameraState('ready_to_capture');
        return;
      }
      Alert.alert('Sem dados', 'O QR Code não contém um contrato de captura offline e não há cache local.');
      setCameraState('qr_error');
      return;
    }

    // error_no_data
    Alert.alert('Sem dados', 'Nenhum dado disponível para esta prova. Conecte-se à internet.');
    setCameraState('qr_error');
  };

  const handleBarCodeScanned = ({ data }: { data: string }) => {
    if (cameraState !== 'scanning_qr') return;

    const parsed = parseQRCode(data);
    if (!parsed) return;

    // Structural validation
    const qrValidation = validateQRPayload(parsed as any);
    if (!qrValidation.isValid) {
      Alert.alert('QR Inválido', qrValidation.errors.join('\n'));
      return;
    }

    setQrData(parsed);
    startScan(parsed);

    updateCurrentScan({
      qrVersion: parsed.v,
      templateId: parsed.tpl_id,
      templateVersion: parsed.tpl_v,
      rowsPerPage: parsed.rpp,
      layoutVersion: parsed.tpl_v ?? 0,
      pageIndex: parsed.p ?? 1,
      pageTotal: parsed.pt ?? 1,
      qStart: parsed.qs ?? 1,
      qEnd: parsed.qe,
      qrGeometry: parsed.g,
      qrOptionCounts: parsed.oc,
    });

    loadExamData(parsed);
  };

  const capturePhoto = async () => {
    if (!cameraRef.current || cameraState === 'capturing' || !resolvedExamId) return;

    setCameraState('capturing');
    if (autoCaptureTimer.current) clearTimeout(autoCaptureTimer.current);

    try {
      const photo = await cameraRef.current.takePictureAsync({ quality: 0.9 });

      if (photo?.uri) {
        const localId = generateLocalId();
        const savedUri = await saveScanImage(photo.uri, localId);

        updateCurrentScan({ localId, imageUri: savedUri });

        router.push({
          pathname: '/scan/adjust',
          params: { examId: String(resolvedExamId), localId, imageUri: savedUri },
        });
      }
    } catch {
      Alert.alert('Erro', 'Não foi possível capturar a foto.');
      setCameraState('ready_to_capture');
    }
  };

  const resetScan = () => {
    setQrData(null);
    setExamTitle('');
    setValidation(null);
    setVersionWarning(null);
    setCameraState('scanning_qr');
  };

  const closeCamera = () => {
    if (router.canGoBack()) {
      router.back();
    } else {
      router.replace('/(tabs)');
    }
  };

  // ── Permission state ──
  if (!permission?.granted) {
    return (
      <View style={styles.permissionContainer}>
        <MaterialIcons name="no-photography" size={64} color={colors.gray} />
        <Text style={styles.permissionText}>Permissão de câmera necessária</Text>
        <Button title="Permitir Câmera" onPress={requestPermission} style={{ marginTop: 24 }} />
      </View>
    );
  }

  return (
    <View style={{ flex: 1, backgroundColor: '#000' }}>
      <CameraView
        ref={cameraRef}
        style={StyleSheet.absoluteFill}
        facing="back"
        enableTorch={flash}
        barcodeScannerSettings={{ barcodeTypes: ['qr'] }}
        onBarcodeScanned={cameraState === 'scanning_qr' ? handleBarCodeScanned : undefined}
      />

      {/* ── Top Bar ── */}
      <View style={[styles.topBar, { top: insets.top }]}>
        <Pressable
          onPress={closeCamera}
          style={styles.topButton}
          accessibilityRole="button"
          accessibilityLabel="Fechar câmera"
        >
          <MaterialIcons name="close" size={24} color={colors.white} />
        </Pressable>

        {/* Mode indicator (shown after QR detected) */}
        {cameraState !== 'scanning_qr' && (
          <ScanModeIndicator
            scanMode={config.scanMode}
            answerSheetType={config.answerSheetType}
            isOnline={isOnline}
            dataStrategy={dataStrategy}
            configVersion={configVersion}
          />
        )}

        <Pressable
          onPress={() => setFlash(!flash)}
          style={[styles.topButton, flash && { backgroundColor: colors.amber }]}
          accessibilityRole="button"
          accessibilityLabel={flash ? 'Desligar flash' : 'Ligar flash'}
        >
          <MaterialIcons name={flash ? 'flash-on' : 'flash-off'} size={24} color={colors.white} />
        </Pressable>
      </View>

      {/* ── State: Scanning QR ── */}
      {cameraState === 'scanning_qr' && (
        <View style={styles.qrOverlay}>
          <View style={styles.qrFrame}>
            <View style={[styles.qrCorner, styles.qrTL]} />
            <View style={[styles.qrCorner, styles.qrTR]} />
            <View style={[styles.qrCorner, styles.qrBL]} />
            <View style={[styles.qrCorner, styles.qrBR]} />
          </View>
          <View style={styles.qrLabel}>
            <Text style={styles.qrLabelText}>
              Aponte para o QR Code da folha de respostas
            </Text>
          </View>
        </View>
      )}

      {/* ── State: Loading Exam ── */}
      {cameraState === 'loading_exam' && (
        <View style={[styles.bottomOverlay, { paddingBottom: insets.bottom + 32 }]}>
          <View style={styles.loadingCard}>
            <ActivityIndicator size="large" color={colors.primary} />
            <Text style={styles.loadingTitle}>Carregando dados da prova...</Text>
            <Text style={styles.loadingSubtitle}>
              Prova #{qrData?.e} · Versão #{qrData?.c}
            </Text>
            <View style={styles.loadingStrategy}>
              <MaterialIcons
                name={dataStrategy === 'use_cache' ? 'storage' : dataStrategy === 'download_on_demand' ? 'cloud-download' : 'qr-code'}
                size={14}
                color={colors.white + '80'}
              />
              <Text style={styles.loadingStrategyText}>
                {dataStrategy === 'use_cache' ? 'Cache local' : dataStrategy === 'download_on_demand' ? 'Baixando do servidor' : 'Dados do QR Code'}
              </Text>
            </View>
          </View>
        </View>
      )}

      {/* ── State: QR Error ── */}
      {cameraState === 'qr_error' && (
        <View style={[styles.bottomOverlay, { paddingBottom: insets.bottom + 32 }]}>
          <View style={styles.errorCard}>
            <MaterialIcons name="error-outline" size={32} color={colors.danger} />
            <Text style={styles.errorTitle}>Erro no QR Code</Text>
            <Button title="Tentar Novamente" onPress={resetScan} variant="outline" size="sm" fullWidth={false} />
          </View>
        </View>
      )}

      {/* ── State: Ready / Auto-capturing ── */}
      {(cameraState === 'ready_to_capture' || cameraState === 'auto_capturing') && (
        <>
          {/* Capture overlay with corner guides and quality */}
          <CaptureOverlay
            validation={validation}
            isAutoCapturing={cameraState === 'auto_capturing'}
            showGuides={true}
          />

          {/* Guidance messages */}
          <GuidanceOverlay
            validation={validation}
            visible={cameraState === 'ready_to_capture'}
          />

          {/* Bottom section */}
          <View style={[styles.bottomOverlay, { paddingBottom: insets.bottom + 16 }]}>
            {/* Version warning */}
            {versionWarning && (
              <View style={styles.warningBadge}>
                <MaterialIcons name="warning" size={14} color={colors.amber} />
                <Text style={styles.warningText}>{versionWarning}</Text>
              </View>
            )}

            {/* Success badge */}
            <View style={styles.successBadge}>
              <MaterialIcons name="check-circle" size={18} color={colors.white} />
              <Text style={styles.successText}>
                QR Detectado · Versão #{qrData?.c}
              </Text>
            </View>

            {/* Exam title */}
            {examTitle ? (
              <View style={styles.examTitleBadge}>
                <Text style={styles.examTitleText}>{examTitle}</Text>
              </View>
            ) : null}

            {/* Instruction */}
            <View style={styles.instructionBadge}>
              <Text style={styles.instructionText}>
                {cameraState === 'auto_capturing'
                  ? 'Captura automática em progresso...'
                  : 'Enquadre o cartão resposta e toque para capturar'}
              </Text>
            </View>

            {/* Capture Button */}
            <Pressable
              onPress={capturePhoto}
              disabled={cameraState === 'auto_capturing'}
              style={[
                styles.captureButton,
                cameraState === 'auto_capturing' && { borderColor: colors.primary, backgroundColor: colors.primary + '30' },
              ]}
              accessibilityRole="button"
              accessibilityLabel="Capturar folha de respostas"
              accessibilityState={{ disabled: cameraState === 'auto_capturing' }}
            >
              <MaterialIcons
                name={cameraState === 'auto_capturing' ? 'autorenew' : 'camera'}
                size={32}
                color={colors.white}
              />
            </Pressable>

            {/* Reset button */}
            <Pressable
              onPress={resetScan}
              style={styles.resetButton}
              accessibilityRole="button"
              accessibilityLabel="Iniciar novo scan"
            >
              <MaterialIcons name="refresh" size={16} color={colors.white + 'aa'} />
              <Text style={styles.resetText}>Novo scan</Text>
            </Pressable>
          </View>
        </>
      )}

      {/* ── State: Capturing (shutter) ── */}
      {cameraState === 'capturing' && (
        <View style={[StyleSheet.absoluteFill, styles.shutterOverlay]}>
          <ActivityIndicator size="large" color={colors.white} />
          <Text style={styles.shutterText}>Processando...</Text>
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  permissionContainer: {
    flex: 1,
    backgroundColor: '#000',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 32,
  },
  permissionText: {
    color: colors.white,
    fontSize: 16,
    fontFamily: fonts.bold,
    textAlign: 'center',
    marginTop: 16,
  },
  topBar: {
    position: 'absolute',
    left: 0,
    right: 0,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 8,
  },
  topButton: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: 'rgba(0,0,0,0.5)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  // QR overlay
  qrOverlay: {
    position: 'absolute',
    top: '22%',
    left: '12%',
    right: '12%',
    height: '22%',
    alignItems: 'center',
    justifyContent: 'center',
  },
  qrFrame: {
    width: '100%',
    height: '100%',
  },
  qrCorner: {
    position: 'absolute',
    width: 28,
    height: 28,
    borderColor: colors.primary,
    borderWidth: 3,
  },
  qrTL: { top: 0, left: 0, borderRightWidth: 0, borderBottomWidth: 0, borderTopLeftRadius: 6 },
  qrTR: { top: 0, right: 0, borderLeftWidth: 0, borderBottomWidth: 0, borderTopRightRadius: 6 },
  qrBL: { bottom: 0, left: 0, borderRightWidth: 0, borderTopWidth: 0, borderBottomLeftRadius: 6 },
  qrBR: { bottom: 0, right: 0, borderLeftWidth: 0, borderTopWidth: 0, borderBottomRightRadius: 6 },
  qrLabel: {
    position: 'absolute',
    bottom: -40,
    left: 0,
    right: 0,
    alignItems: 'center',
  },
  qrLabelText: {
    backgroundColor: 'rgba(0,0,0,0.7)',
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 8,
    color: colors.white,
    fontSize: 13,
    fontFamily: fonts.bold,
    textAlign: 'center',
    overflow: 'hidden',
  },
  // Bottom overlay
  bottomOverlay: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    paddingHorizontal: 24,
    alignItems: 'center',
  },
  // Loading
  loadingCard: {
    backgroundColor: 'rgba(0,0,0,0.8)',
    paddingHorizontal: 24,
    paddingVertical: 16,
    borderRadius: 16,
    alignItems: 'center',
    gap: 10,
  },
  loadingTitle: {
    color: colors.white,
    fontSize: 14,
    fontFamily: fonts.bold,
    textAlign: 'center',
  },
  loadingSubtitle: {
    color: 'rgba(255,255,255,0.5)',
    fontSize: 12,
    fontFamily: fonts.medium,
    textAlign: 'center',
  },
  loadingStrategy: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: 'rgba(255,255,255,0.1)',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 999,
  },
  loadingStrategyText: {
    color: 'rgba(255,255,255,0.6)',
    fontSize: 10,
    fontFamily: fonts.medium,
  },
  // Error
  errorCard: {
    backgroundColor: 'rgba(0,0,0,0.8)',
    paddingHorizontal: 24,
    paddingVertical: 20,
    borderRadius: 16,
    alignItems: 'center',
    gap: 12,
  },
  errorTitle: {
    color: colors.white,
    fontSize: 14,
    fontFamily: fonts.bold,
  },
  // Success / Ready
  warningBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: 'rgba(245,158,11,0.15)',
    borderWidth: 1,
    borderColor: colors.amber + '40',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 8,
    marginBottom: 8,
  },
  warningText: {
    color: colors.amber,
    fontSize: 11,
    fontFamily: fonts.medium,
    flex: 1,
  },
  successBadge: {
    backgroundColor: colors.primary,
    paddingHorizontal: 20,
    paddingVertical: 10,
    borderRadius: 999,
    marginBottom: 8,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  successText: {
    color: colors.white,
    fontSize: 13,
    fontFamily: fonts.bold,
  },
  examTitleBadge: {
    backgroundColor: 'rgba(0,0,0,0.7)',
    paddingHorizontal: 16,
    paddingVertical: 6,
    borderRadius: 8,
    marginBottom: 8,
  },
  examTitleText: {
    color: colors.white,
    fontSize: 12,
    fontFamily: fonts.medium,
    textAlign: 'center',
  },
  instructionBadge: {
    backgroundColor: 'rgba(0,0,0,0.7)',
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 8,
    marginBottom: 16,
  },
  instructionText: {
    color: colors.white,
    fontSize: 13,
    fontFamily: fonts.bold,
    textAlign: 'center',
  },
  captureButton: {
    width: 72,
    height: 72,
    borderRadius: 36,
    borderWidth: 4,
    borderColor: colors.white,
    backgroundColor: colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: colors.primaryDark,
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 1,
    shadowRadius: 0,
  },
  resetButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginTop: 12,
    paddingVertical: 8,
  },
  resetText: {
    color: 'rgba(255,255,255,0.65)',
    fontSize: 12,
    fontFamily: fonts.medium,
  },
  // Shutter
  shutterOverlay: {
    backgroundColor: 'rgba(0,0,0,0.6)',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 16,
  },
  shutterText: {
    color: colors.white,
    fontSize: 16,
    fontFamily: fonts.bold,
  },
});
