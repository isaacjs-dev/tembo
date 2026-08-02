import React, { useCallback, useState } from 'react';
import { View, Text, FlatList, Alert } from 'react-native';
import { MaterialIcons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useExamStore } from '@/store/exam-store';
import { examService } from '@/services/exams';
import { Card } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/EmptyState';
import { colors } from '@/theme/colors';
import { fonts } from '@/theme/typography';
import type { ExamListItem } from '@/types/exam';

export default function FilesScreen() {
  const insets = useSafeAreaInsets();
  const { examList, cachedExams, setExamList, cacheExam, removeCachedExam, setLoadingList, isLoadingList, setDownloading, isDownloading } = useExamStore();

  const loadExams = useCallback(async () => {
    setLoadingList(true);
    try {
      const exams = await examService.list();
      setExamList(exams);
    } catch (error: any) {
      Alert.alert('Erro', 'Nao foi possivel carregar as provas.');
    } finally {
      setLoadingList(false);
    }
  }, []);

  const downloadExam = async (examId: number) => {
    setDownloading(examId);
    try {
      const data = await examService.download(examId);
      await cacheExam(examId, data);
      Alert.alert('Sucesso', 'Prova baixada para uso offline!');
    } catch (error: any) {
      Alert.alert('Erro', 'Nao foi possivel baixar a prova.');
    } finally {
      setDownloading(null);
    }
  };

  const renderExam = ({ item }: { item: ExamListItem }) => {
    const cached = cachedExams.get(item.id);
    const isCached = !!cached;
    const isCurrentlyDownloading = isDownloading === item.id;

    return (
      <Card style={{ marginHorizontal: 16, marginBottom: 12 }}>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', gap: 12 }}>
          <View style={{ flex: 1 }}>
            <Text style={{ fontSize: 16, fontFamily: fonts.bold, color: colors.textPrimary, lineHeight: 22 }}>
              {item.title}
            </Text>
            <Text style={{ fontSize: 12, fontFamily: fonts.medium, color: colors.gray, marginTop: 4 }}>
              {item.classes.map((c) => c.name).join(' \u2022 ') || 'Sem turma'}
            </Text>
            <View style={{ flexDirection: 'row', gap: 8, marginTop: 8 }}>
              <Badge
                label={isCached ? 'Pronto para Digitalizar' : 'Pendente'}
                variant={isCached ? 'success' : 'neutral'}
              />
            </View>
          </View>

          <View style={{ alignItems: 'center', gap: 4 }}>
            {isCached ? (
              <>
                <View style={{ width: 40, height: 40, borderRadius: 20, backgroundColor: colors.primaryLight, alignItems: 'center', justifyContent: 'center' }}>
                  <MaterialIcons name="download-done" size={22} color={colors.primary} />
                </View>
                <Text style={{ fontSize: 9, fontFamily: fonts.bold, color: colors.primary, textTransform: 'uppercase' }}>
                  Offline
                </Text>
              </>
            ) : (
              <Button
                title=""
                accessibilityLabel={`Baixar ${item.title}`}
                onPress={() => downloadExam(item.id)}
                variant="secondary"
                size="sm"
                fullWidth={false}
                loading={isCurrentlyDownloading}
                icon={<MaterialIcons name="download" size={20} color={colors.gray} />}
                style={{ width: 40, height: 40, borderRadius: 20, paddingHorizontal: 0 }}
              />
            )}
          </View>
        </View>
      </Card>
    );
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <View style={{ paddingTop: insets.top + 8, paddingHorizontal: 20, paddingBottom: 16, borderBottomWidth: 2, borderBottomColor: colors.border }}>
        <Text style={{ fontSize: 24, fontFamily: fonts.extraBold, color: colors.textPrimary }}>
          Provas
        </Text>
        <Text style={{ fontSize: 13, fontFamily: fonts.medium, color: colors.textSecondary, marginTop: 4 }}>
          Baixe provas para digitalizar offline
        </Text>
      </View>

      <FlatList
        data={examList}
        keyExtractor={(item) => String(item.id)}
        renderItem={renderExam}
        contentContainerStyle={{ paddingTop: 16, paddingBottom: 100, flexGrow: 1 }}
        onRefresh={loadExams}
        refreshing={isLoadingList}
        ListEmptyComponent={
          <EmptyState
            icon="folder-open"
            title="Nenhuma prova disponivel"
            message="Puxe para baixo para atualizar ou verifique sua conexao."
            actionLabel="Atualizar"
            onAction={loadExams}
          />
        }
      />

      {/* Sync Button */}
      <View style={{ padding: 16, paddingBottom: insets.bottom + 16, backgroundColor: colors.white, borderTopWidth: 2, borderTopColor: colors.border }}>
        <Button
          title="Sincronizar Gabaritos"
          onPress={loadExams}
          icon={<MaterialIcons name="sync" size={20} color={colors.white} />}
          loading={isLoadingList}
        />
      </View>
    </View>
  );
}
