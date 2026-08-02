import React, { useState, useEffect, useCallback } from 'react';
import { View, Text, FlatList, TextInput, Pressable, Alert } from 'react-native';
import { router } from 'expo-router';
import { MaterialIcons } from '@expo/vector-icons';
import { Header } from '@/components/ui/Header';
import { Card } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { useExamStore } from '@/store/exam-store';
import { examService } from '@/services/exams';
import { colors } from '@/theme/colors';
import { fonts } from '@/theme/typography';
import type { ExamListItem } from '@/types/exam';

const FILTERS = ['Todas', 'Ativas', 'Baixadas', 'Finalizadas'] as const;

export default function SelectExamScreen() {
  const { examList, cachedExams, setExamList, cacheExam, setDownloading, isDownloading } = useExamStore();
  const [activeFilter, setActiveFilter] = useState<(typeof FILTERS)[number]>('Todas');
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(false);

  const loadExams = useCallback(async () => {
    setLoading(true);
    try {
      const exams = await examService.list();
      setExamList(exams);
    } catch {
      // Use cached data if offline
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { loadExams(); }, []);

  const filteredExams = examList.filter((exam) => {
    if (activeFilter === 'Baixadas' && !cachedExams.has(exam.id)) return false;
    if (activeFilter === 'Ativas' && exam.status !== 'published') return false;
    if (search.trim()) {
      return exam.title.toLowerCase().includes(search.toLowerCase());
    }
    return true;
  });

  const selectExam = async (exam: ExamListItem) => {
    if (!cachedExams.has(exam.id)) {
      setDownloading(exam.id);
      try {
        const data = await examService.download(exam.id);
        await cacheExam(exam.id, data);
      } catch {
        Alert.alert('Erro', 'Baixe a prova antes de digitalizar.');
        setDownloading(null);
        return;
      }
      setDownloading(null);
    }
    router.push({ pathname: '/scan/camera', params: { examId: String(exam.id) } });
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Header title="Selecionar Prova" showBack />

      {/* Offline Banner */}
      <View style={{ backgroundColor: colors.primaryLight, paddingHorizontal: 16, paddingVertical: 10, borderBottomWidth: 1, borderBottomColor: colors.primary + '20', flexDirection: 'row', alignItems: 'center', gap: 10 }}>
        <View style={{ width: 28, height: 28, borderRadius: 14, backgroundColor: colors.primary, alignItems: 'center', justifyContent: 'center' }}>
          <MaterialIcons name="cloud-off" size={16} color={colors.white} />
        </View>
        <View style={{ flex: 1 }}>
          <Text style={{ fontSize: 12, fontFamily: fonts.bold, color: colors.textPrimary }}>
            Modo Offline: Dados em cache
          </Text>
          <Text style={{ fontSize: 11, fontFamily: fonts.medium, color: colors.textSecondary }}>
            Voce pode digitalizar sem internet.
          </Text>
        </View>
      </View>

      {/* Search */}
      <View style={{ padding: 16 }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', backgroundColor: colors.white, borderWidth: 2, borderColor: colors.border, borderRadius: 12, paddingHorizontal: 12, shadowColor: '#000', shadowOffset: { width: 0, height: 2 }, shadowOpacity: 0.05, shadowRadius: 0, elevation: 2 }}>
          <MaterialIcons name="search" size={20} color={colors.gray} />
          <TextInput
            style={{ flex: 1, height: 44, fontSize: 14, fontFamily: fonts.medium, color: colors.textPrimary, marginLeft: 8 }}
            placeholder="Buscar prova ou turma..."
            placeholderTextColor={colors.gray}
            value={search}
            onChangeText={setSearch}
          />
        </View>
      </View>

      {/* Filter Chips */}
      <View style={{ flexDirection: 'row', paddingHorizontal: 16, gap: 8, marginBottom: 8 }}>
        {FILTERS.map((f) => (
          <Pressable
            key={f}
            onPress={() => setActiveFilter(f)}
            style={{
              paddingHorizontal: 16,
              paddingVertical: 8,
              borderRadius: 999,
              backgroundColor: activeFilter === f ? colors.primary : colors.white,
              borderWidth: activeFilter === f ? 0 : 2,
              borderColor: colors.border,
              shadowColor: activeFilter === f ? colors.primaryDark : colors.border,
              shadowOffset: { width: 0, height: activeFilter === f ? 4 : 2 },
              shadowOpacity: 1,
              shadowRadius: 0,
              elevation: 2,
            }}
          >
            <Text style={{ fontSize: 13, fontFamily: fonts.bold, color: activeFilter === f ? colors.white : colors.textSecondary }}>
              {f}
            </Text>
          </Pressable>
        ))}
      </View>

      {/* Exam List */}
      <FlatList
        data={filteredExams}
        keyExtractor={(item) => String(item.id)}
        contentContainerStyle={{ padding: 16, gap: 12, paddingBottom: 100 }}
        onRefresh={loadExams}
        refreshing={loading}
        renderItem={({ item }) => {
          const isCached = cachedExams.has(item.id);
          return (
            <Card onPress={() => selectExam(item)}>
              <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', gap: 12 }}>
                <View style={{ flex: 1 }}>
                  <Text style={{ fontSize: 16, fontFamily: fonts.bold, color: colors.textPrimary, lineHeight: 22 }}>
                    {item.title}
                  </Text>
                  <Text style={{ fontSize: 12, fontFamily: fonts.medium, color: colors.gray, marginTop: 4 }}>
                    {item.classes.map((c) => c.name).join(' \u2022 ')}
                  </Text>
                  <View style={{ marginTop: 8 }}>
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
                        <MaterialIcons name="download-done" size={20} color={colors.primary} />
                      </View>
                      <Text style={{ fontSize: 9, fontFamily: fonts.bold, color: colors.primary, textTransform: 'uppercase' }}>Offline</Text>
                    </>
                  ) : (
                    <View style={{ width: 40, height: 40, borderRadius: 20, backgroundColor: colors.grayLight, alignItems: 'center', justifyContent: 'center' }}>
                      <MaterialIcons name="download" size={20} color={colors.gray} />
                    </View>
                  )}
                </View>
              </View>
            </Card>
          );
        }}
      />
    </View>
  );
}
