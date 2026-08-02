import React, { useEffect, useCallback, useRef, useState } from 'react';
import { Stack, Redirect } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import {
  useFonts,
  PlusJakartaSans_400Regular,
  PlusJakartaSans_500Medium,
  PlusJakartaSans_600SemiBold,
  PlusJakartaSans_700Bold,
  PlusJakartaSans_800ExtraBold,
} from '@expo-google-fonts/plus-jakarta-sans';
import * as Network from 'expo-network';
import { useAuthStore } from '@/store/auth-store';
import { useSyncStore } from '@/store/sync-store';
import { useScanStore } from '@/store/scan-store';
import { useExamStore } from '@/store/exam-store';
import { useConfigStore } from '@/store/config-store';
import { processQueue } from '@/services/sync-manager';
import { authService } from '@/services/auth';
import { View, ActivityIndicator, AppState } from 'react-native';
import { colors } from '@/theme/colors';
import { getSyncQueueCount } from '@/db/database';

export default function RootLayout() {
  const { hydrate, isLoading, isAuthenticated } = useAuthStore();
  const setOnline = useSyncStore((s) => s.setOnline);
  const networkCheckRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const [appReady, setAppReady] = useState(false);

  const [fontsLoaded] = useFonts({
    PlusJakartaSans_400Regular,
    PlusJakartaSans_500Medium,
    PlusJakartaSans_600SemiBold,
    PlusJakartaSans_700Bold,
    PlusJakartaSans_800ExtraBold,
  });

  const checkNetwork = useCallback(async () => {
    try {
      const state = await Network.getNetworkStateAsync();
      const online = state.isConnected === true && state.isInternetReachable !== false;
      const wasOffline = !useSyncStore.getState().isOnline;
      setOnline(online);

      // Auto-sync when coming back online
      if (online && wasOffline && useAuthStore.getState().isAuthenticated) {
        void processQueue();
        void useConfigStore.getState().fetchEffectiveConfig();
      }
    } catch {
      setOnline(false);
    }
  }, [setOnline]);

  useEffect(() => {
    const bootstrap = async () => {
      await Promise.allSettled([
        useScanStore.getState().hydrate(),
        useSyncStore.getState().hydrate(),
        useExamStore.getState().hydrate(),
        useConfigStore.getState().hydrateFromCache(),
      ]);
      useSyncStore.getState().updatePendingCount(await getSyncQueueCount());

      await hydrate();

      // Validate persisted credentials without blocking offline use. A 401 is
      // handled centrally by the API interceptor and clears the stale session.
      if (useAuthStore.getState().token) {
        try {
          await authService.me();
          void useConfigStore.getState().fetchEffectiveConfig();
        } catch {
          // Network and timeout errors keep the offline session available.
        }
      }

      setAppReady(true);
    };

    void bootstrap();
  }, [hydrate]);

  // Network monitoring
  useEffect(() => {
    checkNetwork();

    // Poll network status every 15 seconds
    networkCheckRef.current = setInterval(checkNetwork, 15000);

    // Also check when app comes to foreground
    const subscription = AppState.addEventListener('change', (state) => {
      if (state === 'active') {
        checkNetwork();
      }
    });

    return () => {
      if (networkCheckRef.current) clearInterval(networkCheckRef.current);
      subscription.remove();
    };
  }, [checkNetwork]);

  if (!fontsLoaded || isLoading || !appReady) {
    return (
      <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.background }}>
        <ActivityIndicator size="large" color={colors.primary} />
      </View>
    );
  }

  if (!isAuthenticated) {
    return (
      <>
        <StatusBar style="dark" />
        <Stack screenOptions={{ headerShown: false }}>
          <Stack.Screen name="(auth)" />
        </Stack>
        <Redirect href="/(auth)/login" />
      </>
    );
  }

  return (
    <>
      <StatusBar style="dark" />
      <Stack screenOptions={{ headerShown: false }}>
        <Stack.Screen name="(tabs)" />
        <Stack.Screen name="scan" options={{ presentation: 'fullScreenModal' }} />
        <Stack.Screen name="scan-detail" />
      </Stack>
    </>
  );
}
