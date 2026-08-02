import React, { useState } from 'react';
import { View, Text, FlatList, Pressable, TextInput, Alert, ActivityIndicator } from 'react-native';
import { router } from 'expo-router';
import { MaterialIcons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useScanStore } from '@/store/scan-store';
import { useSyncStore } from '@/store/sync-store';
import { syncSingleScan } from '@/services/sync-manager';
import { Card } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { EmptyState } from '@/components/ui/EmptyState';
import { colors } from '@/theme/colors';
import { fonts } from '@/theme/typography';
import { formatDate } from '@/utils/format';
import type { ScanResult } from '@/types/scan';

const TABS = ['Todos', 'Pendentes', 'Sincronizados'] as const;

export default function ScansScreen() {
  const insets = useSafeAreaInsets();
  const scans = useScanStore((s) => s.scans);
  const { syncErrors, isOnline } = useSyncStore();
  const [activeTab, setActiveTab] = useState<(typeof TABS)[number]>('Todos');
  const [search, setSearch] = useState('');
  const [syncingId, setSyncingId] = useState<string | null>(null);

  const handleRetryScan = async (localId: string) => {
    if (!isOnline) {
      Alert.alert('Sem conexao', 'Voce esta offline.');
      return;
    }
    setSyncingId(localId);
    const success = await syncSingleScan(localId);
    setSyncingId(null);
    if (success) {
      Alert.alert('Sincronizado', 'Scan enviado com sucesso.');
    } else {
      Alert.alert('Erro', 'Falha ao sincronizar. Tente novamente.');
    }
  };

  const filteredScans = scans.filter((scan) => {
    if (activeTab === 'Pendentes' && scan.status === 'synced') return false;
    if (activeTab === 'Sincronizados' && scan.status !== 'synced') return false;

    if (search.trim()) {
      const q = search.toLowerCase();
      return (
        scan.studentName?.toLowerCase().includes(q) ||
        scan.localId.toLowerCase().includes(q)
      );
    }
    return true;
  });

  const renderScan = ({ item }: { item: ScanResult }) => (
    <Card
      onPress={() => router.push(`/scan-detail/${item.localId}`)}
      style={{ marginHorizontal: 16, marginBottom: 12 }}
    >
      <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start' }}>
        <View style={{ flex: 1 }}>
          <Badge
            label={
              item.status === 'synced' ? 'Sincronizado' :
              item.status === 'confirmed' ? 'Pendente' :
              item.status === 'review' ? 'Em Revisao' : item.status
            }
            variant={
              item.status === 'synced' ? 'success' :
              item.status === 'confirmed' ? 'warning' : 'info'
            }
          />
          <Text style={{ fontSize: 16, fontFamily: fonts.bold, color: colors.textPrimary, marginTop: 8 }}>
            {item.studentName || 'Aluno nao identificado'}
          </Text>
          <Text style={{ fontSize: 12, fontFamily: fonts.medium, color: colors.textSecondary, marginTop: 4 }}>
            ID: #{item.localId.slice(-5).toUpperCase()}
          </Text>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 4, marginTop: 6 }}>
            <MaterialIcons name="schedule" size={14} color={colors.gray} />
            <Text style={{ fontSize: 12, fontFamily: fonts.medium, color: colors.gray }}>
              {formatDate(item.createdAt)}
            </Text>
          </View>
        </View>

        {item.score !== null && (
          <View style={{ alignItems: 'center', padding: 8 }}>
            <Text style={{ fontSize: 22, fontFamily: fonts.extraBold, color: colors.primary }}>
              {item.score?.toFixed(1)}
            </Text>
            <Text style={{ fontSize: 10, fontFamily: fonts.bold, color: colors.gray }}>
              /{item.totalPoints}
            </Text>
          </View>
        )}
      </View>

      {/* Sync error message */}
      {syncErrors[item.localId] && (
        <View style={{ marginTop: 8, flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: colors.danger + '10', padding: 8, borderRadius: 8 }}>
          <MaterialIcons name="error-outline" size={14} color={colors.danger} />
          <Text style={{ flex: 1, fontSize: 11, fontFamily: fonts.medium, color: colors.danger }}>
            {syncErrors[item.localId]}
          </Text>
        </View>
      )}

      <View style={{ flexDirection: 'row', gap: 8, marginTop: 12 }}>
        {/* Retry sync button for pending scans */}
        {item.status === 'confirmed' && (
          <Pressable
            onPress={() => handleRetryScan(item.localId)}
            disabled={syncingId === item.localId}
            style={{
              flex: 1,
              paddingVertical: 10,
              borderRadius: 8,
              backgroundColor: colors.primaryLight,
              alignItems: 'center',
              flexDirection: 'row',
              justifyContent: 'center',
              gap: 6,
            }}
          >
            {syncingId === item.localId ? (
              <ActivityIndicator size="small" color={colors.primary} />
            ) : (
              <MaterialIcons name="sync" size={16} color={colors.primary} />
            )}
            <Text style={{ fontSize: 13, fontFamily: fonts.bold, color: colors.primary }}>
              {syncingId === item.localId ? 'Enviando...' : 'Sincronizar'}
            </Text>
          </Pressable>
        )}
        <Pressable
          onPress={() => router.push(`/scan-detail/${item.localId}`)}
          style={{
            flex: 1,
            paddingVertical: 10,
            borderRadius: 8,
            backgroundColor: item.status === 'synced' ? colors.grayLight : colors.primaryLight,
            alignItems: 'center',
          }}
        >
          <Text style={{ fontSize: 13, fontFamily: fonts.bold, color: item.status === 'synced' ? colors.textSecondary : colors.primary }}>
            Ver Detalhes
          </Text>
        </Pressable>
      </View>
    </Card>
  );

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      {/* Header */}
      <View style={{ paddingTop: insets.top + 8, paddingHorizontal: 20, paddingBottom: 8, borderBottomWidth: 2, borderBottomColor: colors.border, backgroundColor: colors.background }}>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
          <Text style={{ fontSize: 24, fontFamily: fonts.extraBold, color: colors.textPrimary }}>
            Historico
          </Text>
          <MaterialIcons name="filter-list" size={24} color={colors.textSecondary} />
        </View>
      </View>

      {/* Search */}
      <View style={{ paddingHorizontal: 16, paddingVertical: 12 }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', backgroundColor: colors.white, borderWidth: 2, borderColor: colors.border, borderRadius: 12, paddingHorizontal: 12 }}>
          <MaterialIcons name="search" size={20} color={colors.gray} />
          <TextInput
            style={{ flex: 1, height: 44, fontSize: 14, fontFamily: fonts.medium, color: colors.textPrimary, marginLeft: 8 }}
            placeholder="Buscar por aluno ou prova..."
            placeholderTextColor={colors.gray}
            value={search}
            onChangeText={setSearch}
          />
        </View>
      </View>

      {/* Tabs */}
      <View style={{ flexDirection: 'row', paddingHorizontal: 16, gap: 8, marginBottom: 12 }}>
        {TABS.map((tab) => (
          <Pressable
            key={tab}
            onPress={() => setActiveTab(tab)}
            style={{
              paddingHorizontal: 16,
              paddingVertical: 8,
              borderRadius: 999,
              backgroundColor: activeTab === tab ? colors.primary : colors.white,
              borderWidth: activeTab === tab ? 0 : 2,
              borderColor: colors.border,
            }}
          >
            <Text
              style={{
                fontSize: 13,
                fontFamily: fonts.bold,
                color: activeTab === tab ? colors.white : colors.textSecondary,
              }}
            >
              {tab}
            </Text>
          </Pressable>
        ))}
      </View>

      {/* List */}
      <FlatList
        data={filteredScans}
        keyExtractor={(item) => item.localId}
        renderItem={renderScan}
        contentContainerStyle={{ paddingBottom: 100, flexGrow: 1 }}
        ListEmptyComponent={
          <EmptyState
            icon="history"
            title="Nenhum scan encontrado"
            message="Seus scans aparecerao aqui apos digitalizar folhas de resposta."
            actionLabel="Digitalizar Agora"
            onAction={() => router.push('/scan/camera')}
          />
        }
      />
    </View>
  );
}
