import React from 'react';
import { View, Text, Pressable, StyleSheet } from 'react-native';
import { MaterialIcons } from '@expo/vector-icons';
import { colors } from '@/theme/colors';
import { fonts } from '@/theme/typography';
import { getCaptureGuidance, type PreCaptureValidation } from '@/lib/capture-engine';

interface GuidanceOverlayProps {
  validation: PreCaptureValidation | null;
  visible: boolean;
}

export function GuidanceOverlay({ validation, visible }: GuidanceOverlayProps) {
  if (!visible || !validation) return null;

  const messages = getCaptureGuidance(validation);
  const isReady = validation.qualityScore >= 0.75;

  return (
    <View style={styles.container}>
      <View style={[styles.card, isReady && styles.cardReady]}>
        {messages.map((msg, i) => (
          <Text
            key={i}
            style={[styles.message, isReady && styles.messageReady]}
          >
            {msg}
          </Text>
        ))}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    position: 'absolute',
    bottom: '24%',
    left: 24,
    right: 24,
    alignItems: 'center',
  },
  card: {
    backgroundColor: 'rgba(0,0,0,0.75)',
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.1)',
    gap: 4,
    maxWidth: 300,
  },
  cardReady: {
    backgroundColor: 'rgba(85,202,2,0.15)',
    borderColor: colors.primary + '60',
  },
  message: {
    color: colors.white,
    fontSize: 12,
    fontFamily: fonts.medium,
    textAlign: 'center',
  },
  messageReady: {
    color: colors.primary,
    fontFamily: fonts.bold,
  },
});
