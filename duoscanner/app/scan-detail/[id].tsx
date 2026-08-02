import React, { useState } from 'react';
import { View, Text, ScrollView, Image, Pressable, Alert } from 'react-native';
import { useLocalSearchParams, router } from 'expo-router';
import { MaterialIcons } from '@expo/vector-icons';
import { Header } from '@/components/ui/Header';
import { Card } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { useScanStore } from '@/store/scan-store';
import { useSyncStore } from '@/store/sync-store';
import { syncSingleScan } from '@/services/sync-manager';
import { getSyncQueueCount } from '@/db/database';
import { colors } from '@/theme/colors';
import { fonts } from '@/theme/typography';
import { formatDate } from '@/utils/format';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

const LETTERS = ['A', 'B', 'C', 'D', 'E'];

export default function ScanDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const insets = useSafeAreaInsets();
  const scan = useScanStore((s) => s.scans.find((s) => s.localId === id));
  const removeScan = useScanStore((s) => s.removeScan);
  const setCurrentScan = useScanStore((s) => s.setCurrentScan);
  const isOnline = useSyncStore((s) => s.isOnline);
  const [showAllAnswers, setShowAllAnswers] = useState(false);
  const [syncing, setSyncing] = useState(false);

  if (!scan) {
    return (
      <View style={{ flex: 1, backgroundColor: colors.background, alignItems: 'center', justifyContent: 'center' }}>
        <Text style={{ fontSize: 16, fontFamily: fonts.bold, color: colors.textSecondary }}>
          Scan nao encontrado
        </Text>
      </View>
    );
  }

  const statusConfig = {
    synced: { label: 'Sincronizado', variant: 'success' as const, icon: 'cloud-done' },
    confirmed: { label: 'Pendente', variant: 'warning' as const, icon: 'cloud-upload' },
    review: { label: 'Em Revisao', variant: 'info' as const, icon: 'rate-review' },
    processing: { label: 'Processando', variant: 'info' as const, icon: 'hourglass-empty' },
    rejected: { label: 'Rejeitado', variant: 'danger' as const, icon: 'cancel' },
  };

  const status = statusConfig[scan.status] || statusConfig.review;
  const answers = scan.confirmedAnswers || scan.detectedAnswers || {};
  const answerEntries = Object.entries(answers).slice(0, showAllAnswers ? undefined : 8);

  const handleDelete = () => {
    Alert.alert('Excluir scan?', 'O histórico local deste scan será removido.', [
      { text: 'Cancelar', style: 'cancel' },
      {
        text: 'Excluir',
        style: 'destructive',
        onPress: () => {
          void removeScan(scan.localId).then(async () => {
            useSyncStore.getState().updatePendingCount(await getSyncQueueCount());
            if (router.canGoBack()) router.back();
            else router.replace('/(tabs)/scans');
          });
        },
      },
    ]);
  };

  const handleRetry = async () => {
    if (!isOnline) {
      Alert.alert('Sem conexão', 'Conecte-se à internet para sincronizar.');
      return;
    }
    setSyncing(true);
    const success = await syncSingleScan(scan.localId);
    setSyncing(false);
    Alert.alert(success ? 'Sincronizado' : 'Pendente', success
      ? 'Scan enviado com sucesso.'
      : 'Não foi possível enviar agora. O scan continua salvo localmente.');
  };

  const handleEdit = () => {
    setCurrentScan(scan);
    router.push({
      pathname: '/scan/manual-edit',
      params: { examId: String(scan.examId), localId: scan.localId, imageUri: scan.imageUri },
    });
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Header
        title="Detalhes da Digitalizacao"
        showBack
        rightAction={
          <Pressable
            onPress={handleDelete}
            hitSlop={4}
            accessibilityRole="button"
            accessibilityLabel="Excluir scan"
            style={{ width: 40, height: 40, alignItems: 'center', justifyContent: 'center' }}
          >
            <MaterialIcons name="delete-outline" size={22} color={colors.danger} />
          </Pressable>
        }
      />

      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 120, gap: 16 }}>
        {/* Student Info */}
        <Card>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
            <View style={{ width: 52, height: 52, borderRadius: 26, backgroundColor: colors.primaryLight, alignItems: 'center', justifyContent: 'center' }}>
              <MaterialIcons name="person" size={28} color={colors.primary} />
            </View>
            <View style={{ flex: 1 }}>
              <Text style={{ fontSize: 18, fontFamily: fonts.bold, color: colors.textPrimary }}>
                {scan.studentName || 'Nao identificado'}
              </Text>
              <Text style={{ fontSize: 12, fontFamily: fonts.medium, color: colors.textSecondary }}>
                ID #{scan.localId.slice(-5).toUpperCase()}
              </Text>
            </View>
            <Badge label={status.label} variant={status.variant} />
          </View>

          <View style={{ flexDirection: 'row', gap: 16, marginTop: 16, paddingTop: 12, borderTopWidth: 1, borderTopColor: colors.grayLight }}>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
              <MaterialIcons name="schedule" size={16} color={colors.gray} />
              <Text style={{ fontSize: 12, fontFamily: fonts.medium, color: colors.textSecondary }}>
                {formatDate(scan.createdAt)}
              </Text>
            </View>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
              <MaterialIcons name="assignment" size={16} color={colors.gray} />
              <Text style={{ fontSize: 12, fontFamily: fonts.medium, color: colors.textSecondary }}>
                Prova #{scan.examId}
              </Text>
            </View>
          </View>
        </Card>

        {/* Score */}
        {scan.score !== null && (
          <Card>
            <View style={{ alignItems: 'center', paddingVertical: 8 }}>
              <Text style={{ fontSize: 36, fontFamily: fonts.extraBold, color: colors.primary }}>
                {scan.score?.toFixed(1)}
              </Text>
              <Text style={{ fontSize: 14, fontFamily: fonts.bold, color: colors.gray }}>
                / {scan.totalPoints}
              </Text>
            </View>
          </Card>
        )}

        {/* Scanned Image */}
        {scan.imageUri && (
          <View>
            <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
              <Text style={{ fontSize: 12, fontFamily: fonts.bold, color: colors.textSecondary, textTransform: 'uppercase' }}>
                Gabarito Escaneado
              </Text>
              <Text style={{ fontSize: 12, fontFamily: fonts.bold, color: colors.primary }}>
                {Math.round((scan.confidenceScore || 0) * 100)}% Legivel
              </Text>
            </View>
            <View style={{ height: 180, borderRadius: 12, overflow: 'hidden', borderWidth: 2, borderColor: colors.border }}>
              <Image source={{ uri: scan.imageUri }} style={{ width: '100%', height: '100%' }} resizeMode="cover" />
            </View>
          </View>
        )}

        {/* Detected Answers */}
        <View>
          <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
            <Text style={{ fontSize: 12, fontFamily: fonts.bold, color: colors.textSecondary, textTransform: 'uppercase' }}>
              Respostas Detectadas
            </Text>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 4 }}>
              <View style={{ width: 8, height: 8, borderRadius: 4, backgroundColor: colors.primary }} />
              <Text style={{ fontSize: 11, fontFamily: fonts.medium, color: colors.textSecondary }}>
                Confianca alta
              </Text>
            </View>
          </View>

          <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
            {answerEntries.map(([qId, answer], index) => (
              <View
                key={qId}
                style={{
                  width: '23%',
                  backgroundColor: colors.white,
                  borderWidth: 2,
                  borderColor: colors.border,
                  borderRadius: 12,
                  padding: 10,
                  alignItems: 'center',
                }}
              >
                <Text style={{ fontSize: 10, fontFamily: fonts.bold, color: colors.gray }}>
                  {String(index + 1).padStart(2, '0')}
                </Text>
                <Text style={{ fontSize: 18, fontFamily: fonts.extraBold, color: colors.primary, marginTop: 2 }}>
                  {answer !== null ? LETTERS[answer as number] || '?' : '-'}
                </Text>
                <View style={{ width: 6, height: 6, borderRadius: 3, backgroundColor: colors.primary, marginTop: 4 }} />
              </View>
            ))}
          </View>

          {Object.keys(answers).length > 8 && (
            <Pressable
              onPress={() => setShowAllAnswers(true)}
              accessibilityRole="button"
              accessibilityLabel="Mostrar todas as respostas"
              style={{ alignItems: 'center', paddingVertical: 12 }}
            >
              <Text style={{ fontSize: 13, fontFamily: fonts.bold, color: colors.primary }}>
                Ver todas as {Object.keys(answers).length} questoes
              </Text>
            </Pressable>
          )}
        </View>
      </ScrollView>

      {/* Bottom Actions */}
      <View style={{ position: 'absolute', bottom: 0, left: 0, right: 0, backgroundColor: colors.white, borderTopWidth: 2, borderTopColor: colors.border, padding: 16, paddingBottom: insets.bottom + 16, flexDirection: 'row', gap: 12 }}>
        {scan.status !== 'synced' && (
          <Button
            title="Reenviar Dados"
            onPress={() => void handleRetry()}
            variant="outline"
            fullWidth={false}
            loading={syncing}
            disabled={syncing}
            icon={<MaterialIcons name="sync" size={18} color={colors.primary} />}
            style={{ flex: 1 }}
          />
        )}
        <Button
          title="Editar Manual"
          onPress={handleEdit}
          fullWidth={false}
          icon={<MaterialIcons name="edit" size={18} color={colors.white} />}
          style={{ flex: 1 }}
        />
      </View>
    </View>
  );
}
