import React from 'react';
import { View, Text, ScrollView, Alert } from 'react-native';
import { MaterialIcons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAuthStore } from '@/store/auth-store';
import { useSyncStore } from '@/store/sync-store';
import { useScanStore } from '@/store/scan-store';
import { authService } from '@/services/auth';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { colors } from '@/theme/colors';
import { fonts } from '@/theme/typography';

export default function ProfileScreen() {
  const insets = useSafeAreaInsets();
  const { user, logout: clearAuth } = useAuthStore();
  const { isOnline, lastSyncAt, pendingCount } = useSyncStore();
  const scans = useScanStore((s) => s.scans);

  const handleLogout = () => {
    Alert.alert('Sair', 'Deseja realmente sair do Tembo Scanner?', [
      { text: 'Cancelar', style: 'cancel' },
      {
        text: 'Sair',
        style: 'destructive',
        onPress: async () => {
          try {
            await authService.logout();
          } catch {
            // Logout even if API fails
          }
          await clearAuth();
        },
      },
    ]);
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <View style={{ paddingTop: insets.top + 8, paddingHorizontal: 20, paddingBottom: 16, borderBottomWidth: 2, borderBottomColor: colors.border }}>
        <Text style={{ fontSize: 24, fontFamily: fonts.extraBold, color: colors.textPrimary }}>
          Perfil
        </Text>
      </View>

      <ScrollView contentContainerStyle={{ padding: 20, gap: 16, paddingBottom: 100 }}>
        {/* User Info */}
        <Card>
          <View style={{ alignItems: 'center', paddingVertical: 8 }}>
            <View style={{ width: 72, height: 72, borderRadius: 36, backgroundColor: colors.primaryLight, alignItems: 'center', justifyContent: 'center', marginBottom: 12 }}>
              <MaterialIcons name="person" size={36} color={colors.primary} />
            </View>
            <Text style={{ fontSize: 20, fontFamily: fonts.extraBold, color: colors.textPrimary }}>
              {user?.name}
            </Text>
            <Text style={{ fontSize: 13, fontFamily: fonts.medium, color: colors.textSecondary, marginTop: 4 }}>
              {user?.email}
            </Text>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 4, marginTop: 8 }}>
              <MaterialIcons name="school" size={16} color={colors.primary} />
              <Text style={{ fontSize: 13, fontFamily: fonts.semiBold, color: colors.primary }}>
                {user?.organization?.name || 'Sem instituicao'}
              </Text>
            </View>
          </View>
        </Card>

        {/* Sync Status */}
        <Card>
          <Text style={{ fontSize: 14, fontFamily: fonts.extraBold, color: colors.textPrimary, marginBottom: 12, textTransform: 'uppercase', letterSpacing: 0.5 }}>
            Status de Sincronizacao
          </Text>
          <View style={{ gap: 12 }}>
            <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
              <Text style={{ fontSize: 13, fontFamily: fonts.medium, color: colors.textSecondary }}>
                Conexao
              </Text>
              <View style={{ flexDirection: 'row', alignItems: 'center', gap: 4 }}>
                <View style={{ width: 8, height: 8, borderRadius: 4, backgroundColor: isOnline ? colors.primary : colors.danger }} />
                <Text style={{ fontSize: 13, fontFamily: fonts.bold, color: isOnline ? colors.primary : colors.danger }}>
                  {isOnline ? 'Online' : 'Offline'}
                </Text>
              </View>
            </View>

            <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
              <Text style={{ fontSize: 13, fontFamily: fonts.medium, color: colors.textSecondary }}>
                Scans na fila
              </Text>
              <Text style={{ fontSize: 13, fontFamily: fonts.bold, color: colors.textPrimary }}>
                {pendingCount}
              </Text>
            </View>

            <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
              <Text style={{ fontSize: 13, fontFamily: fonts.medium, color: colors.textSecondary }}>
                Total de scans
              </Text>
              <Text style={{ fontSize: 13, fontFamily: fonts.bold, color: colors.textPrimary }}>
                {scans.length}
              </Text>
            </View>

            <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
              <Text style={{ fontSize: 13, fontFamily: fonts.medium, color: colors.textSecondary }}>
                Ultima sincronizacao
              </Text>
              <Text style={{ fontSize: 13, fontFamily: fonts.bold, color: colors.textPrimary }}>
                {lastSyncAt || 'Nunca'}
              </Text>
            </View>
          </View>
        </Card>

        {/* Logout */}
        <Button
          title="Sair da Conta"
          onPress={handleLogout}
          variant="danger"
          icon={<MaterialIcons name="logout" size={20} color={colors.white} />}
        />

        {/* Version */}
        <Text style={{ textAlign: 'center', fontSize: 11, fontFamily: fonts.medium, color: colors.gray }}>
          Tembo Scanner v1.0.0
        </Text>
      </ScrollView>
    </View>
  );
}
