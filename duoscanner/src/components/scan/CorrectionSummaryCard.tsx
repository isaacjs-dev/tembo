import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { MaterialIcons } from '@expo/vector-icons';
import { Card } from '@/components/ui/Card';
import { colors } from '@/theme/colors';
import { fonts } from '@/theme/typography';
import type { CorrectionResult } from '@/lib/correction-engine';

interface CorrectionSummaryCardProps {
  result: CorrectionResult;
  studentName?: string;
}

export function CorrectionSummaryCard({ result, studentName }: CorrectionSummaryCardProps) {
  return (
    <Card variant="elevated" style={styles.card}>
      {/* Header stats row */}
      <View style={styles.statsRow}>
        <StatBlock
          icon="check-circle"
          iconColor={colors.primary}
          value={result.correctCount}
          label="Acertos"
          bg={colors.primaryLight}
        />
        <StatBlock
          icon="cancel"
          iconColor={colors.danger}
          value={result.incorrectCount}
          label="Erros"
          bg={colors.danger + '12'}
        />
        <StatBlock
          icon="remove-circle"
          iconColor={colors.gray}
          value={result.blankCount}
          label="Em branco"
          bg={colors.grayLight}
        />
        {result.reviewCount > 0 && (
          <StatBlock
            icon="warning"
            iconColor={colors.amber}
            value={result.reviewCount}
            label="Revisar"
            bg={colors.amberLight}
          />
        )}
      </View>

      {/* Divider */}
      <View style={styles.divider} />

      {/* Score + percentage */}
      <View style={styles.scoreRow}>
        <View style={styles.scoreMain}>
          <Text style={styles.scoreValue}>{result.score}</Text>
          <Text style={styles.scoreSeparator}>/</Text>
          <Text style={styles.scoreTotal}>{result.totalPoints}</Text>
        </View>
        <View style={[styles.percentageBadge, { backgroundColor: getPercentColor(result.percentage) + '15' }]}>
          <Text style={[styles.percentageText, { color: getPercentColor(result.percentage) }]}>
            {result.percentage}%
          </Text>
        </View>
      </View>

      {/* Mode info */}
      <View style={styles.modeRow}>
        <View style={styles.modePill}>
          <MaterialIcons
            name={result.answerSheetType === 'essential' ? 'description' : 'grid-on'}
            size={12}
            color={colors.textMuted}
          />
          <Text style={styles.modeText}>
            {result.answerSheetType === 'essential' ? 'Essential' : 'Detalhado'}
          </Text>
        </View>
      </View>
    </Card>
  );
}

function StatBlock({
  icon,
  iconColor,
  value,
  label,
  bg,
}: {
  icon: string;
  iconColor: string;
  value: number;
  label: string;
  bg: string;
}) {
  return (
    <View style={[styles.statBlock, { backgroundColor: bg }]}>
      <MaterialIcons name={icon as any} size={16} color={iconColor} />
      <Text style={[styles.statValue, { color: iconColor }]}>{value}</Text>
      <Text style={styles.statLabel}>{label}</Text>
    </View>
  );
}

function getPercentColor(p: number) {
  if (p >= 70) return colors.primary;
  if (p >= 50) return colors.amber;
  return colors.danger;
}

const styles = StyleSheet.create({
  card: {
    paddingVertical: 20,
    paddingHorizontal: 16,
  },
  statsRow: {
    flexDirection: 'row',
    justifyContent: 'center',
    gap: 8,
  },
  statBlock: {
    flex: 1,
    alignItems: 'center',
    paddingVertical: 10,
    paddingHorizontal: 4,
    borderRadius: 10,
    gap: 2,
  },
  statValue: {
    fontSize: 18,
    fontFamily: fonts.extraBold,
  },
  statLabel: {
    fontSize: 9,
    fontFamily: fonts.bold,
    color: colors.textMuted,
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  divider: {
    height: 1,
    backgroundColor: colors.grayLight,
    marginVertical: 16,
  },
  scoreRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 12,
  },
  scoreMain: {
    flexDirection: 'row',
    alignItems: 'baseline',
    gap: 4,
  },
  scoreValue: {
    fontSize: 32,
    fontFamily: fonts.extraBold,
    color: colors.textPrimary,
  },
  scoreSeparator: {
    fontSize: 20,
    fontFamily: fonts.bold,
    color: colors.gray,
  },
  scoreTotal: {
    fontSize: 20,
    fontFamily: fonts.bold,
    color: colors.gray,
  },
  percentageBadge: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 8,
  },
  percentageText: {
    fontSize: 16,
    fontFamily: fonts.extraBold,
  },
  modeRow: {
    marginTop: 12,
    alignItems: 'center',
  },
  modePill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 999,
    backgroundColor: colors.grayLight,
  },
  modeText: {
    fontSize: 10,
    fontFamily: fonts.bold,
    color: colors.textMuted,
  },
});
