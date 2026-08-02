import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  Image,
  Pressable,
  Alert,
  Dimensions,
  ActivityIndicator,
  StyleSheet,
} from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { MaterialIcons } from '@expo/vector-icons';
import * as ImageManipulator from 'expo-image-manipulator';
import {
  GestureDetector,
  Gesture,
  GestureHandlerRootView,
} from 'react-native-gesture-handler';
import Animated, {
  useSharedValue,
  useAnimatedStyle,
  withSpring,
} from 'react-native-reanimated';
import { Header } from '@/components/ui/Header';
import { Button } from '@/components/ui/Button';
import { useScanStore } from '@/store/scan-store';
import { colors } from '@/theme/colors';
import { fonts } from '@/theme/typography';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

const { width: SCREEN_WIDTH } = Dimensions.get('window');
const VIEWPORT_W = SCREEN_WIDTH - 48;
const VIEWPORT_H = VIEWPORT_W * 1.45;

export default function AdjustScreen() {
  const { examId, localId, imageUri: originalUri } = useLocalSearchParams<{
    examId: string;
    localId: string;
    imageUri: string;
  }>();
  const insets = useSafeAreaInsets();
  const { updateCurrentScan } = useScanStore();

  const [baseUri, setBaseUri] = useState('');
  const [baseSize, setBaseSize] = useState({ w: 900, h: 1200 });
  const [ready, setReady] = useState(false);
  const [processing, setProcessing] = useState(false);
  const [rotating, setRotating] = useState(false);

  // Gesture shared values
  const scale = useSharedValue(1);
  const savedScale = useSharedValue(1);
  const tx = useSharedValue(0);
  const savedTx = useSharedValue(0);
  const ty = useSharedValue(0);
  const savedTy = useSharedValue(0);
  const rot = useSharedValue(0);
  const savedRot = useSharedValue(0);

  // On mount: resize original to standard width (fast, <500ms)
  useEffect(() => {
    if (!originalUri) return;
    (async () => {
      try {
        const r = await ImageManipulator.manipulateAsync(
          originalUri,
          [{ resize: { width: 900 } }],
          { compress: 0.9, format: ImageManipulator.SaveFormat.JPEG }
        );
        setBaseUri(r.uri);
        setBaseSize({ w: r.width, h: r.height });
      } catch {
        setBaseUri(originalUri);
      }
      setReady(true);
    })();
  }, [originalUri]);

  const resetGestures = useCallback(() => {
    scale.value = withSpring(1);
    savedScale.value = 1;
    tx.value = withSpring(0);
    savedTx.value = 0;
    ty.value = withSpring(0);
    savedTy.value = 0;
    rot.value = withSpring(0);
    savedRot.value = 0;
  }, []);

  // === GESTURES ===
  const pinch = Gesture.Pinch()
    .onUpdate((e) => { scale.value = savedScale.value * e.scale; })
    .onEnd(() => {
      savedScale.value = Math.max(0.5, Math.min(5, scale.value));
      if (scale.value < 0.5) scale.value = withSpring(0.5);
      if (scale.value > 5) scale.value = withSpring(5);
    });

  const pan = Gesture.Pan()
    .minPointers(1).maxPointers(1)
    .onUpdate((e) => {
      tx.value = savedTx.value + e.translationX;
      ty.value = savedTy.value + e.translationY;
    })
    .onEnd(() => { savedTx.value = tx.value; savedTy.value = ty.value; });

  const rotGesture = Gesture.Rotation()
    .onUpdate((e) => { rot.value = savedRot.value + e.rotation; })
    .onEnd(() => { savedRot.value = rot.value; });

  const composed = Gesture.Simultaneous(pinch, rotGesture, pan);

  const animStyle = useAnimatedStyle(() => ({
    transform: [
      { translateX: tx.value },
      { translateY: ty.value },
      { scale: scale.value },
      { rotate: `${rot.value}rad` },
    ],
  }));

  // === ACTIONS ===
  const handleRotate90 = async () => {
    if (!baseUri || rotating) return;
    setRotating(true);
    try {
      const r = await ImageManipulator.manipulateAsync(
        baseUri,
        [{ rotate: 90 }],
        { compress: 0.9, format: ImageManipulator.SaveFormat.JPEG }
      );
      setBaseUri(r.uri);
      setBaseSize({ w: r.width, h: r.height });
      resetGestures();
    } catch {
      Alert.alert('Erro', 'Falha ao girar.');
    }
    setRotating(false);
  };

  const handleProcess = async () => {
    if (!baseUri) return;
    setProcessing(true);
    try {
      const actions: ImageManipulator.Action[] = [];

      // Apply manual rotation if any
      const rotDeg = rot.value * (180 / Math.PI);
      if (Math.abs(rotDeg) > 0.5) {
        actions.push({ rotate: Math.round(rotDeg) });
      }

      // Calculate crop from pan/zoom
      const s = scale.value;
      const imgW = baseSize.w;
      const imgH = baseSize.h;

      // How the image maps to the viewport (contain mode)
      const imgAspect = imgW / imgH;
      const vpAspect = VIEWPORT_W / VIEWPORT_H;
      const displayW = imgAspect > vpAspect ? VIEWPORT_W : VIEWPORT_H * imgAspect;
      const pxScale = imgW / displayW; // display px -> image px

      const visW = (VIEWPORT_W * pxScale) / s;
      const visH = (VIEWPORT_H * pxScale) / s;
      const cx = imgW / 2 - (tx.value * pxScale) / s;
      const cy = imgH / 2 - (ty.value * pxScale) / s;

      let cropX = Math.round(cx - visW / 2);
      let cropY = Math.round(cy - visH / 2);
      let cropW = Math.round(visW);
      let cropH = Math.round(visH);

      // Only apply crop if user actually zoomed/panned
      if (s > 1.05 || Math.abs(tx.value) > 5 || Math.abs(ty.value) > 5) {
        cropX = Math.max(0, cropX);
        cropY = Math.max(0, cropY);
        cropW = Math.min(cropW, imgW - cropX);
        cropH = Math.min(cropH, imgH - cropY);
        if (cropW > 10 && cropH > 10) {
          actions.push({ crop: { originX: cropX, originY: cropY, width: cropW, height: cropH } });
        }
      }

      // Final resize
      actions.push({ resize: { width: 900 } });

      const result = await ImageManipulator.manipulateAsync(
        baseUri,
        actions,
        { compress: 0.9, format: ImageManipulator.SaveFormat.JPEG }
      );

      updateCurrentScan({ imageUri: result.uri });
      router.push({
        pathname: '/scan/review-marks',
        params: { examId, localId, imageUri: result.uri },
      });
    } catch (error) {
      console.warn('Process error:', error);
      Alert.alert('Erro', 'Falha ao processar imagem.');
    }
    setProcessing(false);
  };

  return (
    <GestureHandlerRootView style={{ flex: 1 }}>
      <View style={{ flex: 1, backgroundColor: colors.background }}>
        <Header title="Ajustar Imagem" showBack />

        {/* Hint */}
        <View style={s.hintBar}>
          <MaterialIcons name="info-outline" size={14} color={colors.textSecondary} />
          <Text style={s.hintBarText}>
            Enquadre o cartao resposta. Use gestos para ajustar.
          </Text>
        </View>

        {/* Viewport */}
        <View style={s.vpContainer}>
          <View style={s.viewport}>
            {ready ? (
              <GestureDetector gesture={composed}>
                <Animated.View style={[s.imgWrap, animStyle]}>
                  <Image source={{ uri: baseUri }} style={s.img} resizeMode="contain" />
                </Animated.View>
              </GestureDetector>
            ) : (
              <View style={s.loading}>
                <ActivityIndicator size="large" color={colors.primary} />
                <Text style={s.loadingText}>Preparando...</Text>
              </View>
            )}
          </View>

          {/* Gesture hints */}
          <View style={s.hints}>
            <HintChip icon="pinch" label="Zoom" />
            <HintChip icon="open-with" label="Mover" />
            <HintChip icon="screen-rotation" label="Girar (2 dedos)" />
          </View>
        </View>

        {/* Actions */}
        <View style={s.actions}>
          <ActionButton icon="replay" label="REDEFINIR" onPress={resetGestures} disabled={rotating} />
          <ActionButton icon="rotate-right" label="GIRAR 90°" onPress={handleRotate90} disabled={rotating} loading={rotating} />
        </View>

        {/* Process */}
        <View style={[s.bottom, { paddingBottom: insets.bottom + 16 }]}>
          <Button
            title="Processar Imagem"
            onPress={handleProcess}
            size="lg"
            loading={processing}
            disabled={!ready || processing || rotating}
            icon={<MaterialIcons name="arrow-forward" size={20} color={colors.white} />}
          />
        </View>
      </View>
    </GestureHandlerRootView>
  );
}

function HintChip({ icon, label }: { icon: string; label: string }) {
  return (
    <View style={s.hintChip}>
      <MaterialIcons name={icon as any} size={13} color={colors.textMuted} />
      <Text style={s.hintChipText}>{label}</Text>
    </View>
  );
}

function ActionButton({ icon, label, onPress, disabled, loading }: {
  icon: string; label: string; onPress: () => void; disabled?: boolean; loading?: boolean;
}) {
  return (
    <Pressable onPress={onPress} disabled={disabled} style={[s.actBtn, disabled && { opacity: 0.4 }]}>
      <View style={s.actIcon}>
        {loading ? (
          <ActivityIndicator size="small" color={colors.textPrimary} />
        ) : (
          <MaterialIcons name={icon as any} size={22} color={colors.textPrimary} />
        )}
      </View>
      <Text style={s.actLabel}>{label}</Text>
    </Pressable>
  );
}

const s = StyleSheet.create({
  hintBar: { flexDirection: 'row', alignItems: 'center', gap: 6, paddingHorizontal: 16, paddingVertical: 8, backgroundColor: colors.grayLight },
  hintBarText: { fontSize: 12, fontFamily: fonts.medium, color: colors.textSecondary },
  vpContainer: { flex: 1, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 24, paddingVertical: 8 },
  viewport: { width: VIEWPORT_W, height: VIEWPORT_H, borderRadius: 12, overflow: 'hidden', borderWidth: 2, borderColor: colors.border, backgroundColor: '#111' },
  imgWrap: { width: '100%', height: '100%' },
  img: { width: '100%', height: '100%' },
  loading: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  loadingText: { color: colors.white, fontSize: 13, fontFamily: fonts.bold, marginTop: 8 },
  hints: { flexDirection: 'row', gap: 16, marginTop: 8 },
  hintChip: { flexDirection: 'row', alignItems: 'center', gap: 3 },
  hintChipText: { fontSize: 10, fontFamily: fonts.medium, color: colors.textMuted },
  actions: { flexDirection: 'row', justifyContent: 'center', gap: 24, paddingVertical: 10 },
  actBtn: { alignItems: 'center', gap: 3 },
  actIcon: { width: 42, height: 42, borderRadius: 21, backgroundColor: colors.white, borderWidth: 2, borderColor: colors.border, alignItems: 'center', justifyContent: 'center' },
  actLabel: { fontSize: 10, fontFamily: fonts.bold, color: colors.textSecondary },
  bottom: { padding: 16, backgroundColor: colors.white, borderTopWidth: 2, borderTopColor: colors.border },
});
