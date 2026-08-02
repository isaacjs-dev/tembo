import React from 'react';
import {
  Pressable,
  Text,
  ActivityIndicator,
  StyleSheet,
  ViewStyle,
  TextStyle,
} from 'react-native';
import Animated, {
  useAnimatedStyle,
  useSharedValue,
  withTiming,
} from 'react-native-reanimated';
import { colors } from '@/theme/colors';
import { fonts } from '@/theme/typography';

const AnimatedPressable = Animated.createAnimatedComponent(Pressable);

interface ButtonProps {
  title: string;
  onPress: () => void;
  variant?: 'primary' | 'secondary' | 'outline' | 'danger' | 'login';
  size?: 'sm' | 'md' | 'lg';
  icon?: React.ReactNode;
  loading?: boolean;
  disabled?: boolean;
  fullWidth?: boolean;
  style?: ViewStyle;
  accessibilityLabel?: string;
}

export function Button({
  title,
  onPress,
  variant = 'primary',
  size = 'md',
  icon,
  loading = false,
  disabled = false,
  fullWidth = true,
  style,
  accessibilityLabel,
}: ButtonProps) {
  const pressed = useSharedValue(0);

  const colorMap = {
    primary: { bg: colors.primary, shadow: colors.primaryDark, text: colors.white },
    secondary: { bg: colors.white, shadow: colors.border, text: colors.textPrimary },
    outline: { bg: 'transparent', shadow: colors.primaryDark, text: colors.primary },
    danger: { bg: colors.danger, shadow: colors.dangerDark, text: colors.white },
    login: { bg: colors.loginOrange, shadow: colors.loginOrangeDark, text: colors.white },
  };

  const sizeMap = {
    sm: { height: 40, fontSize: 13, paddingHorizontal: 16 },
    md: { height: 48, fontSize: 15, paddingHorizontal: 24 },
    lg: { height: 56, fontSize: 17, paddingHorizontal: 32 },
  };

  const c = colorMap[variant];
  const s = sizeMap[size];

  const animatedStyle = useAnimatedStyle(() => ({
    transform: [{ translateY: pressed.value * 4 }],
  }));

  return (
    <AnimatedPressable
      onPressIn={() => { pressed.value = withTiming(1, { duration: 50 }); }}
      onPressOut={() => { pressed.value = withTiming(0, { duration: 100 }); }}
      onPress={onPress}
      disabled={disabled || loading}
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel || title}
      accessibilityState={{ disabled: disabled || loading, busy: loading }}
      style={[
        {
          backgroundColor: variant === 'outline' ? 'transparent' : c.bg,
          borderWidth: variant === 'outline' ? 2 : 0,
          borderColor: variant === 'outline' ? colors.primary : undefined,
          height: s.height,
          paddingHorizontal: s.paddingHorizontal,
          borderRadius: 12,
          flexDirection: 'row',
          alignItems: 'center',
          justifyContent: 'center',
          gap: 8,
          width: fullWidth ? '100%' : undefined,
          opacity: disabled ? 0.5 : 1,
          shadowColor: c.shadow,
          shadowOpacity: 1,
          shadowRadius: 0,
          elevation: 4,
        },
        animatedStyle,
        style,
      ]}
    >
      {loading ? (
        <ActivityIndicator color={c.text} size="small" />
      ) : (
        <>
          {icon}
          <Text
            style={{
              color: c.text,
              fontSize: s.fontSize,
              fontFamily: fonts.extraBold,
              textTransform: 'uppercase',
              letterSpacing: 1,
            }}
          >
            {title}
          </Text>
        </>
      )}
    </AnimatedPressable>
  );
}
