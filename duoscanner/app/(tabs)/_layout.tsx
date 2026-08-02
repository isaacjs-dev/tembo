import React from 'react';
import { View, Pressable, StyleSheet } from 'react-native';
import { Tabs, router } from 'expo-router';
import { MaterialIcons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { colors } from '@/theme/colors';
import { fonts } from '@/theme/typography';
import { useScanStore } from '@/store/scan-store';

function CameraFAB() {
  return (
    <View style={styles.fabContainer}>
      <Pressable
        onPress={() => router.push('/scan/camera')}
        accessibilityRole="button"
        accessibilityLabel="Abrir câmera para digitalizar prova"
        style={({ pressed }) => [
          styles.fab,
          pressed && { transform: [{ translateY: 4 }], shadowOffset: { width: 0, height: 0 } },
        ]}
      >
        <MaterialIcons name="photo-camera" size={28} color={colors.white} />
      </Pressable>
    </View>
  );
}

export default function TabsLayout() {
  const insets = useSafeAreaInsets();
  const pendingCount = useScanStore((s) => s.pendingCount);

  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarStyle: {
          height: 60 + insets.bottom,
          paddingBottom: insets.bottom,
          borderTopWidth: 1,
          borderTopColor: colors.primaryLight,
          backgroundColor: colors.background,
          elevation: 0,
          shadowOpacity: 0,
        },
        tabBarActiveTintColor: colors.primary,
        tabBarInactiveTintColor: colors.gray,
        tabBarLabelStyle: {
          fontSize: 10,
          fontFamily: fonts.bold,
          marginTop: -2,
        },
      }}
    >
      <Tabs.Screen
        name="index"
        options={{
          title: 'Inicio',
          tabBarIcon: ({ color, size }) => (
            <MaterialIcons name="home" size={size} color={color} />
          ),
        }}
      />
      <Tabs.Screen
        name="scans"
        options={{
          title: 'Scans',
          tabBarIcon: ({ color, size }) => (
            <MaterialIcons name="history" size={size} color={color} />
          ),
          tabBarBadge: pendingCount > 0 ? pendingCount : undefined,
          tabBarBadgeStyle: {
            backgroundColor: colors.primary,
            fontSize: 10,
            fontFamily: fonts.bold,
          },
        }}
      />
      <Tabs.Screen
        name="camera-placeholder"
        options={{
          title: '',
          tabBarButton: () => <CameraFAB />,
        }}
        listeners={{
          tabPress: (e) => {
            e.preventDefault();
            router.push('/scan/camera');
          },
        }}
      />
      <Tabs.Screen
        name="files"
        options={{
          title: 'Arquivos',
          tabBarIcon: ({ color, size }) => (
            <MaterialIcons name="folder" size={size} color={color} />
          ),
        }}
      />
      <Tabs.Screen
        name="profile"
        options={{
          title: 'Perfil',
          tabBarIcon: ({ color, size }) => (
            <MaterialIcons name="person" size={size} color={color} />
          ),
        }}
      />
    </Tabs>
  );
}

const styles = StyleSheet.create({
  fabContainer: {
    top: -24,
    justifyContent: 'center',
    alignItems: 'center',
    width: 64,
  },
  fab: {
    width: 56,
    height: 56,
    borderRadius: 28,
    backgroundColor: colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: colors.primaryDark,
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 1,
    shadowRadius: 0,
    elevation: 8,
  },
});
