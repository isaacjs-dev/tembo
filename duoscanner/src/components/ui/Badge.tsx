import React from 'react';
import { View, Text } from 'react-native';
import { colors } from '@/theme/colors';
import { fonts } from '@/theme/typography';

type BadgeVariant = 'success' | 'warning' | 'danger' | 'info' | 'neutral';

interface BadgeProps {
  label: string;
  variant?: BadgeVariant;
}

const variantStyles: Record<BadgeVariant, { bg: string; text: string; border: string }> = {
  success: { bg: colors.primaryLight, text: colors.primary, border: colors.primary + '30' },
  warning: { bg: colors.amberLight, text: '#92400e', border: '#f59e0b30' },
  danger: { bg: '#fef2f2', text: colors.danger, border: colors.danger + '30' },
  info: { bg: '#eff6ff', text: '#2563eb', border: '#2563eb30' },
  neutral: { bg: colors.grayLight, text: colors.textSecondary, border: colors.border },
};

export function Badge({ label, variant = 'neutral' }: BadgeProps) {
  const v = variantStyles[variant];

  return (
    <View
      style={{
        backgroundColor: v.bg,
        borderWidth: 1,
        borderColor: v.border,
        borderRadius: 999,
        paddingHorizontal: 12,
        paddingVertical: 4,
        alignSelf: 'flex-start',
      }}
    >
      <Text
        style={{
          color: v.text,
          fontSize: 11,
          fontFamily: fonts.bold,
        }}
      >
        {label}
      </Text>
    </View>
  );
}
