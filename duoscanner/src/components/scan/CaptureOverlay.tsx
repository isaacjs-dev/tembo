import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import Animated, {
  useAnimatedStyle,
  withRepeat,
  withSequence,
  withTiming,
  useSharedValue,
} from 'react-native-reanimated';
import { colors } from '@/theme/colors';
import { fonts } from '@/theme/typography';
import type { PreCaptureValidation } from '@/lib/capture-engine';

interface CaptureOverlayProps {
  validation: PreCaptureValidation | null;
  isAutoCapturing: boolean;
  showGuides: boolean;
}

export function CaptureOverlay({
  validation,
  isAutoCapturing,
  showGuides,
}: CaptureOverlayProps) {
  const pulseAnim = useSharedValue(1);

  React.useEffect(() => {
    if (isAutoCapturing) {
      pulseAnim.value = withRepeat(
        withSequence(
          withTiming(1.05, { duration: 400 }),
          withTiming(0.95, { duration: 400 })
        ),
        -1,
        true
      );
    } else {
      pulseAnim.value = withTiming(1, { duration: 200 });
    }
  }, [isAutoCapturing]);

  const frameStyle = useAnimatedStyle(() => ({
    transform: [{ scale: pulseAnim.value }],
  }));

  const qualityScore = validation?.qualityScore ?? 0;
  const borderColor = qualityScore >= 0.75
    ? colors.primary
    : qualityScore >= 0.5
    ? colors.amber
    : colors.danger;

  return (
    <View style={StyleSheet.absoluteFill} pointerEvents="none">
      {/* Corner guides */}
      {showGuides && (
        <Animated.View style={[styles.frameContainer, frameStyle]}>
          {/* Top-left */}
          <View style={[styles.corner, styles.topLeft, { borderColor }]} />
          {/* Top-right */}
          <View style={[styles.corner, styles.topRight, { borderColor }]} />
          {/* Bottom-left */}
          <View style={[styles.corner, styles.bottomLeft, { borderColor }]} />
          {/* Bottom-right */}
          <View style={[styles.corner, styles.bottomRight, { borderColor }]} />

          {/* Center crosshair */}
          <View style={styles.crosshairContainer}>
            <View style={[styles.crosshairH, { backgroundColor: borderColor + '40' }]} />
            <View style={[styles.crosshairV, { backgroundColor: borderColor + '40' }]} />
          </View>
        </Animated.View>
      )}

      {/* Quality indicators */}
      {validation && (
        <View style={styles.indicatorsContainer}>
          <View style={[styles.indicatorRow]}>
            <QualityDot ok={validation.edgesDetected} label="Bordas" />
            <QualityDot ok={validation.markersDetected} label="Marcadores" />
            <QualityDot ok={validation.focusOk} label="Foco" />
            <QualityDot ok={validation.tiltOk} label="Inclinação" />
            <QualityDot ok={validation.lightingOk} label="Luz" />
          </View>
        </View>
      )}

      {/* Quality score bar */}
      {validation && (
        <View style={styles.scoreBarContainer}>
          <View style={styles.scoreBarTrack}>
            <Animated.View
              style={[
                styles.scoreBarFill,
                {
                  width: `${Math.round(qualityScore * 100)}%`,
                  backgroundColor: borderColor,
                },
              ]}
            />
          </View>
          <Text style={[styles.scoreText, { color: borderColor }]}>
            {Math.round(qualityScore * 100)}%
          </Text>
        </View>
      )}

      {/* Auto-capture countdown */}
      {isAutoCapturing && (
        <View style={styles.autoCaptureOverlay}>
          <View style={styles.autoCaptureBadge}>
            <View style={[styles.autoCaptureDot, { backgroundColor: colors.primary }]} />
            <Text style={styles.autoCaptureText}>Captura automática...</Text>
          </View>
        </View>
      )}
    </View>
  );
}

function QualityDot({ ok, label }: { ok: boolean; label: string }) {
  return (
    <View style={styles.dotContainer}>
      <View style={[styles.dot, { backgroundColor: ok ? colors.primary : colors.danger + '80' }]} />
      <Text style={[styles.dotLabel, { color: ok ? colors.primary : colors.danger + '80' }]}>
        {label}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  frameContainer: {
    position: 'absolute',
    top: '15%',
    left: '8%',
    right: '8%',
    bottom: '25%',
  },
  corner: {
    position: 'absolute',
    width: 36,
    height: 36,
    borderWidth: 3,
  },
  topLeft: {
    top: 0,
    left: 0,
    borderRightWidth: 0,
    borderBottomWidth: 0,
    borderTopLeftRadius: 8,
  },
  topRight: {
    top: 0,
    right: 0,
    borderLeftWidth: 0,
    borderBottomWidth: 0,
    borderTopRightRadius: 8,
  },
  bottomLeft: {
    bottom: 0,
    left: 0,
    borderRightWidth: 0,
    borderTopWidth: 0,
    borderBottomLeftRadius: 8,
  },
  bottomRight: {
    bottom: 0,
    right: 0,
    borderLeftWidth: 0,
    borderTopWidth: 0,
    borderBottomRightRadius: 8,
  },
  crosshairContainer: {
    position: 'absolute',
    top: '50%',
    left: '50%',
    width: 24,
    height: 24,
    marginLeft: -12,
    marginTop: -12,
    alignItems: 'center',
    justifyContent: 'center',
  },
  crosshairH: {
    position: 'absolute',
    width: 24,
    height: 1,
  },
  crosshairV: {
    position: 'absolute',
    width: 1,
    height: 24,
  },
  indicatorsContainer: {
    position: 'absolute',
    top: '12%',
    left: 0,
    right: 0,
    alignItems: 'center',
  },
  indicatorRow: {
    flexDirection: 'row',
    gap: 12,
    backgroundColor: 'rgba(0,0,0,0.65)',
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
  },
  dotContainer: {
    alignItems: 'center',
    gap: 3,
  },
  dot: {
    width: 8,
    height: 8,
    borderRadius: 4,
  },
  dotLabel: {
    fontSize: 9,
    fontFamily: fonts.bold,
  },
  scoreBarContainer: {
    position: 'absolute',
    bottom: '23%',
    left: '12%',
    right: '12%',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  scoreBarTrack: {
    flex: 1,
    height: 4,
    backgroundColor: 'rgba(255,255,255,0.2)',
    borderRadius: 2,
    overflow: 'hidden',
  },
  scoreBarFill: {
    height: '100%',
    borderRadius: 2,
  },
  scoreText: {
    fontSize: 11,
    fontFamily: fonts.bold,
    minWidth: 32,
  },
  autoCaptureOverlay: {
    position: 'absolute',
    top: '50%',
    left: 0,
    right: 0,
    alignItems: 'center',
    marginTop: -20,
  },
  autoCaptureBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: 'rgba(0,0,0,0.75)',
    paddingHorizontal: 20,
    paddingVertical: 10,
    borderRadius: 999,
  },
  autoCaptureDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
  },
  autoCaptureText: {
    color: colors.white,
    fontSize: 13,
    fontFamily: fonts.bold,
  },
});
