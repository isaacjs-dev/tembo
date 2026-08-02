import React, { useState } from 'react';
import { View, Text, ScrollView, Pressable, Alert } from 'react-native';
import { router } from 'expo-router';
import { MaterialIcons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAuthStore } from '@/store/auth-store';
import { useScanStore } from '@/store/scan-store';
import { useSyncStore } from '@/store/sync-store';
import { processQueue } from '@/services/sync-manager';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { colors } from '@/theme/colors';
import { fonts } from '@/theme/typography';
import { formatDate } from '@/utils/format';

export default function HomeScreen() {
  const insets = useSafeAreaInsets();
  const user = useAuthStore((s) => s.user);
  const scans = useScanStore((s) => s.scans);
  const scanPendingCount = useScanStore((s) => s.pendingCount);
  const { isOnline, pendingCount: syncPendingCount, isSyncing, lastSyncAt } = useSyncStore();
  const [localSyncing, setLocalSyncing] = useState(false);

  const todayScans = scans.filter((s) => {
    const today = new Date().toDateString();
    return new Date(s.createdAt).toDateString() === today;
  });

  const handleSyncAll = async () => {
    if (!isOnline) {
      Alert.alert('Sem conexao', 'Voce esta offline. Conecte-se a internet para sincronizar.');
      return;
    }
    if (syncPendingCount === 0) {
      Alert.alert('Tudo sincronizado', 'Nao ha scans pendentes na fila.');
      return;
    }

    setLocalSyncing(true);
    try {
      const result = await processQueue();
      if (result.synced > 0) {
        Alert.alert(
          'Sincronizado',
          `${result.synced} scan(s) enviado(s) com sucesso.${result.failed > 0 ? ` ${result.failed} falharam.` : ''}`
        );
      } else if (result.failed > 0) {
        Alert.alert('Erro', `${result.failed} scan(s) falharam ao sincronizar. Tente novamente.`);
      }
    } catch {
      Alert.alert('Erro', 'Falha ao sincronizar. Tente novamente.');
    } finally {
      setLocalSyncing(false);
    }
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      {/* Header */}
      <View
        style={{
          paddingTop: insets.top + 8,
          paddingHorizontal: 20,
          paddingBottom: 16,
          backgroundColor: colors.background,
          borderBottomWidth: 2,
          borderBottomColor: colors.border,
        }}
      >
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
          <View>
            <Text style={{ fontSize: 14, fontFamily: fonts.medium, color: colors.textSecondary }}>
              Ola, {user?.name?.split(' ')[0]}!
            </Text>
            <Text style={{ fontSize: 22, fontFamily: fonts.extraBold, color: colors.textPrimary, letterSpacing: -0.5 }}>
              Tembo Scanner
            </Text>
          </View>
          <View style={{ flexDirection: 'row', gap: 8, alignItems: 'center' }}>
            <View
              style={{
                width: 10,
                height: 10,
                borderRadius: 5,
                backgroundColor: isOnline ? colors.primary : colors.danger,
              }}
            />
            <Text style={{ fontSize: 11, fontFamily: fonts.bold, color: isOnline ? colors.primary : colors.danger }}>
              {isOnline ? 'Online' : 'Offline'}
            </Text>
          </View>
        </View>
      </View>

      <ScrollView contentContainerStyle={{ padding: 20, gap: 16, paddingBottom: 100 }}>
        {/* Stats Cards */}
        <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 12 }}>
          <Card style={{ flex: 1, minWidth: 110 }}>
            <View style={{ alignItems: 'center', gap: 4 }}>
              <MaterialIcons name="qr-code-scanner" size={28} color={colors.primary} />
              <Text style={{ fontSize: 28, fontFamily: fonts.extraBold, color: colors.textPrimary }}>
                {todayScans.length}
              </Text>
              <Text style={{ fontSize: 11, fontFamily: fonts.bold, color: colors.textSecondary, textTransform: 'uppercase' }}>
                Scans Hoje
              </Text>
            </View>
          </Card>

          <Card style={{ flex: 1, minWidth: 110 }}>
            <View style={{ alignItems: 'center', gap: 4 }}>
              <MaterialIcons name="pending" size={28} color={scanPendingCount > 0 ? colors.amber : colors.primary} />
              <Text style={{ fontSize: 28, fontFamily: fonts.extraBold, color: colors.textPrimary }}>
                {scanPendingCount}
              </Text>
              <Text style={{ fontSize: 11, fontFamily: fonts.bold, color: colors.textSecondary, textTransform: 'uppercase' }}>
                Pendentes
              </Text>
            </View>
          </Card>

          <Card style={{ flex: 1, minWidth: 110 }}>
            <View style={{ alignItems: 'center', gap: 4 }}>
              <MaterialIcons name="cloud-upload" size={28} color={syncPendingCount > 0 ? colors.amber : colors.primary} />
              <Text style={{ fontSize: 28, fontFamily: fonts.extraBold, color: colors.textPrimary }}>
                {syncPendingCount}
              </Text>
              <Text style={{ fontSize: 11, fontFamily: fonts.bold, color: colors.textSecondary, textTransform: 'uppercase' }}>
                Fila Sync
              </Text>
            </View>
          </Card>
        </View>

        {/* Quick Action */}
        <Button
          title="Digitalizar Prova"
          onPress={() => router.push('/scan/camera')}
          size="lg"
          icon={<MaterialIcons name="document-scanner" size={22} color={colors.white} />}
        />

        {/* Sync Button - shown when queue has items */}
        {syncPendingCount > 0 && (
          <Button
            title={localSyncing || isSyncing ? 'Sincronizando...' : `Sincronizar Tudo (${syncPendingCount})`}
            onPress={handleSyncAll}
            variant="outline"
            loading={localSyncing || isSyncing}
            disabled={localSyncing || isSyncing}
            icon={<MaterialIcons name="sync" size={20} color={colors.primary} />}
          />
        )}

        {/* Last sync info */}
        {lastSyncAt && (
          <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 4 }}>
            <MaterialIcons name="check-circle" size={14} color={colors.primary} />
            <Text style={{ fontSize: 11, fontFamily: fonts.medium, color: colors.textSecondary }}>
              Ultima sync: {formatDate(lastSyncAt)}
            </Text>
          </View>
        )}

        {/* Organization */}
        {user?.organization && (
          <Card>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
              <View style={{ width: 44, height: 44, borderRadius: 12, backgroundColor: colors.primaryLight, alignItems: 'center', justifyContent: 'center' }}>
                <MaterialIcons name="school" size={24} color={colors.primary} />
              </View>
              <View style={{ flex: 1 }}>
                <Text style={{ fontSize: 12, fontFamily: fonts.bold, color: colors.textSecondary, textTransform: 'uppercase' }}>
                  Instituicao
                </Text>
                <Text style={{ fontSize: 16, fontFamily: fonts.bold, color: colors.textPrimary }}>
                  {user.organization.name}
                </Text>
              </View>
            </View>
          </Card>
        )}

        {/* Recent Scans */}
        {todayScans.length > 0 && (
          <View>
            <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
              <Text style={{ fontSize: 16, fontFamily: fonts.extraBold, color: colors.textPrimary }}>
                Scans Recentes
              </Text>
              <Pressable onPress={() => router.push('/(tabs)/scans')}>
                <Text style={{ fontSize: 13, fontFamily: fonts.bold, color: colors.primary }}>
                  Ver Todos
                </Text>
              </Pressable>
            </View>
            {todayScans.slice(0, 3).map((scan) => (
              <Card
                key={scan.localId}
                onPress={() => router.push(`/scan-detail/${scan.localId}`)}
                style={{ marginBottom: 8 }}
              >
                <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
                  <View style={{ flex: 1 }}>
                    <Text style={{ fontSize: 15, fontFamily: fonts.bold, color: colors.textPrimary }}>
                      {scan.studentName || 'Aluno nao identificado'}
                    </Text>
                    <Text style={{ fontSize: 12, fontFamily: fonts.medium, color: colors.textSecondary }}>
                      {scan.score !== null ? `Nota: ${scan.score}/${scan.totalPoints}` : 'Processando...'}
                    </Text>
                  </View>
                  <View
                    style={{
                      width: 32,
                      height: 32,
                      borderRadius: 16,
                      backgroundColor: scan.status === 'synced' ? colors.primaryLight : colors.amberLight,
                      alignItems: 'center',
                      justifyContent: 'center',
                    }}
                  >
                    <MaterialIcons
                      name={scan.status === 'synced' ? 'cloud-done' : 'cloud-upload'}
                      size={18}
                      color={scan.status === 'synced' ? colors.primary : colors.amber}
                    />
                  </View>
                </View>
              </Card>
            ))}
          </View>
        )}
      </ScrollView>
    </View>
  );
}
