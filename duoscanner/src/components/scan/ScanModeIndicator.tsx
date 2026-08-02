import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { MaterialIcons } from '@expo/vector-icons';
import { colors } from '@/theme/colors';
import { fonts } from '@/theme/typography';

interface ScanModeIndicatorProps {
  scanMode: 'preloaded' | 'qr_embedded' | 'hybrid';
  answerSheetType: 'essential' | 'detailed';
  isOnline: boolean;
  dataStrategy: 'use_cache' | 'download_on_demand' | 'use_qr_fallback' | 'error_no_data';
  configVersion: number;
}

const MODE_LABELS: Record<string, { label: string; icon: string; color: string }> = {
  preloaded: { label: 'Pré-carregado', icon: 'cloud-download', color: colors.primary },
  qr_embedded: { label: 'Via QR Code', icon: 'qr-code-2', color: colors.amber },
  hybrid: { label: 'Híbrido', icon: 'sync-alt', color: '#8b5cf6' },
};

const STRATEGY_LABELS: Record<string, { label: string; icon: string }> = {
  use_cache: { label: 'Cache local', icon: 'storage' },
  download_on_demand: { label: 'Download', icon: 'cloud-download' },
  use_qr_fallback: { label: 'Fallback QR', icon: 'qr-code' },
  error_no_data: { label: 'Sem dados', icon: 'error-outline' },
};

export function ScanModeIndicator({
  scanMode,
  answerSheetType,
  isOnline,
  dataStrategy,
  configVersion,
}: ScanModeIndicatorProps) {
  const mode = MODE_LABELS[scanMode] || MODE_LABELS.hybrid;
  const strategy = STRATEGY_LABELS[dataStrategy] || STRATEGY_LABELS.error_no_data;

  return (
    <View style={styles.container}>
      {/* Mode pill */}
      <View style={[styles.pill, { borderColor: mode.color + '80' }]}>
        <MaterialIcons name={mode.icon as any} size={12} color={mode.color} />
        <Text style={[styles.pillText, { color: mode.color }]}>{mode.label}</Text>
      </View>

      {/* Sheet type pill */}
      <View style={[styles.pill, { borderColor: colors.textMuted + '40' }]}>
        <MaterialIcons
          name={answerSheetType === 'essential' ? 'description' : 'grid-on'}
          size={12}
          color={colors.textMuted}
        />
        <Text style={[styles.pillText, { color: colors.textMuted }]}>
          {answerSheetType === 'essential' ? 'Essential' : 'Detalhado'}
        </Text>
      </View>

      {/* Online/offline */}
      <View style={[styles.statusDot, { backgroundColor: isOnline ? colors.primary : colors.danger }]} />

      {/* Data strategy */}
      <View style={styles.strategyBadge}>
        <MaterialIcons name={strategy.icon as any} size={10} color={colors.white + 'cc'} />
        <Text style={styles.strategyText}>{strategy.label}</Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    flexWrap: 'wrap',
  },
  pill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 999,
    borderWidth: 1,
    backgroundColor: 'rgba(0,0,0,0.5)',
  },
  pillText: {
    fontSize: 10,
    fontFamily: fonts.bold,
  },
  statusDot: {
    width: 7,
    height: 7,
    borderRadius: 4,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.3)',
  },
  strategyBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 3,
    paddingHorizontal: 6,
    paddingVertical: 3,
    borderRadius: 4,
    backgroundColor: 'rgba(0,0,0,0.5)',
  },
  strategyText: {
    fontSize: 9,
    fontFamily: fonts.medium,
    color: 'rgba(255,255,255,0.7)',
  },
});
