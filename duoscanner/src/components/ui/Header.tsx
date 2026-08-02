import React from 'react';
import { View, Text, Pressable } from 'react-native';
import { router } from 'expo-router';
import { MaterialIcons } from '@expo/vector-icons';
import { colors } from '@/theme/colors';
import { fonts } from '@/theme/typography';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

interface HeaderProps {
  title: string;
  showBack?: boolean;
  rightAction?: React.ReactNode;
}

export function Header({ title, showBack = false, rightAction }: HeaderProps) {
  const insets = useSafeAreaInsets();
  const goBack = () => {
    if (router.canGoBack()) {
      router.back();
    } else {
      router.replace('/(tabs)');
    }
  };

  return (
    <View
      style={{
        paddingTop: insets.top,
        backgroundColor: colors.background + 'cc',
        borderBottomWidth: 2,
        borderBottomColor: colors.border,
      }}
    >
      <View
        style={{
          height: 56,
          flexDirection: 'row',
          alignItems: 'center',
          paddingHorizontal: 16,
          gap: 12,
        }}
      >
        {showBack && (
          <Pressable
            onPress={goBack}
            accessibilityRole="button"
            accessibilityLabel="Voltar"
            style={{
              width: 40,
              height: 40,
              borderRadius: 12,
              alignItems: 'center',
              justifyContent: 'center',
            }}
          >
            <MaterialIcons name="arrow-back" size={24} color={colors.textSecondary} />
          </Pressable>
        )}

        <Text
          style={{
            flex: 1,
            fontSize: 18,
            fontFamily: fonts.extraBold,
            color: colors.textPrimary,
            textAlign: showBack ? 'center' : 'left',
            letterSpacing: -0.3,
          }}
        >
          {title}
        </Text>

        {rightAction || (showBack && <View style={{ width: 40 }} />)}
      </View>
    </View>
  );
}
