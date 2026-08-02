import React from 'react';
import { View, Pressable, ViewStyle } from 'react-native';
import { colors } from '@/theme/colors';

interface CardProps {
  children: React.ReactNode;
  onPress?: () => void;
  style?: ViewStyle;
  variant?: 'default' | 'elevated';
}

export function Card({ children, onPress, style, variant = 'default' }: CardProps) {
  const baseStyle: ViewStyle = {
    backgroundColor: colors.white,
    borderWidth: 2,
    borderColor: colors.border,
    borderRadius: 12,
    padding: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: variant === 'elevated' ? 4 : 2 },
    shadowOpacity: 0.05,
    shadowRadius: 0,
    elevation: variant === 'elevated' ? 4 : 2,
  };

  if (onPress) {
    return (
      <Pressable
        onPress={onPress}
        style={({ pressed }) => [
          baseStyle,
          pressed && { borderColor: colors.primary + '80' },
          style,
        ]}
      >
        {children}
      </Pressable>
    );
  }

  return <View style={[baseStyle, style]}>{children}</View>;
}
