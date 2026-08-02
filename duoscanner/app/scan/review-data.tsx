import React, { useState } from 'react';
import { View, Text, ScrollView, Image, Pressable, TextInput } from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { MaterialIcons } from '@expo/vector-icons';
import { Header } from '@/components/ui/Header';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Card } from '@/components/ui/Card';
import { useExamStore } from '@/store/exam-store';
import { useScanStore } from '@/store/scan-store';
import { colors } from '@/theme/colors';
import { fonts } from '@/theme/typography';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

export default function ReviewDataScreen() {
  const { examId, localId, imageUri } = useLocalSearchParams<{
    examId: string;
    localId: string;
    imageUri: string;
  }>();
  const insets = useSafeAreaInsets();
  const cachedExam = useExamStore((s) => s.getCachedExam(Number(examId)));
  const { currentScan, updateCurrentScan } = useScanStore();
  const students = cachedExam?.data.students || [];

  const [selectedStudent, setSelectedStudent] = useState<number | null>(currentScan?.studentId || null);
  const [studentSearch, setStudentSearch] = useState('');

  const filteredStudents = students.filter((s) =>
    s.name.toLowerCase().includes(studentSearch.toLowerCase())
  );

  const handleConfirm = () => {
    const student = students.find((s) => s.id === selectedStudent);
    updateCurrentScan({
      studentId: selectedStudent,
      studentName: student?.name || null,
    });
    router.push({
      pathname: '/scan/result',
      params: { examId, localId },
    });
  };

  const handleSkip = () => {
    updateCurrentScan({
      studentId: null,
      studentName: null,
    });
    router.push({
      pathname: '/scan/result',
      params: { examId, localId },
    });
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Header title="Revisar Dados" showBack />

      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 120, gap: 16 }}>
        {/* Student Info */}
        <View>
          <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
            <Text style={{ fontSize: 16, fontFamily: fonts.extraBold, color: colors.textPrimary }}>
              Informacoes do Aluno
            </Text>
            <Badge label="Selecionar" variant="info" />
          </View>

          {/* Search students */}
          <View style={{ flexDirection: 'row', alignItems: 'center', backgroundColor: colors.white, borderWidth: 2, borderColor: colors.border, borderRadius: 12, paddingHorizontal: 12, marginBottom: 12 }}>
            <MaterialIcons name="search" size={18} color={colors.gray} />
            <TextInput
              style={{ flex: 1, height: 44, fontSize: 14, fontFamily: fonts.medium, color: colors.textPrimary, marginLeft: 8 }}
              placeholder="Buscar aluno..."
              placeholderTextColor={colors.gray}
              value={studentSearch}
              onChangeText={setStudentSearch}
            />
          </View>

          {/* Student List */}
          {filteredStudents.slice(0, 20).map((student) => (
            <Pressable
              key={student.id}
              onPress={() => setSelectedStudent(student.id)}
              style={{
                flexDirection: 'row',
                alignItems: 'center',
                padding: 12,
                backgroundColor: selectedStudent === student.id ? colors.primaryLight : colors.white,
                borderWidth: 2,
                borderColor: selectedStudent === student.id ? colors.primary : colors.border,
                borderRadius: 12,
                marginBottom: 8,
                gap: 12,
              }}
            >
              <View style={{ width: 36, height: 36, borderRadius: 18, backgroundColor: selectedStudent === student.id ? colors.primary + '20' : colors.grayLight, alignItems: 'center', justifyContent: 'center' }}>
                <MaterialIcons name="person" size={18} color={selectedStudent === student.id ? colors.primary : colors.gray} />
              </View>
              <View style={{ flex: 1 }}>
                <Text style={{ fontSize: 14, fontFamily: fonts.bold, color: colors.textPrimary }}>
                  {student.name}
                </Text>
                {student.registration_number && (
                  <Text style={{ fontSize: 11, fontFamily: fonts.medium, color: colors.textSecondary }}>
                    Matricula: {student.registration_number}
                  </Text>
                )}
              </View>
              {selectedStudent === student.id && (
                <MaterialIcons name="check-circle" size={22} color={colors.primary} />
              )}
            </Pressable>
          ))}
        </View>

        {/* Image Preview */}
        {imageUri && (
          <View>
            <Text style={{ fontSize: 14, fontFamily: fonts.extraBold, color: colors.textPrimary, marginBottom: 8 }}>
              Miniatura do Gabarito
            </Text>
            <View style={{ height: 180, borderRadius: 12, overflow: 'hidden', borderWidth: 2, borderColor: colors.border }}>
              <Image source={{ uri: imageUri }} style={{ width: '100%', height: '100%' }} resizeMode="cover" />
            </View>
          </View>
        )}
      </ScrollView>

      {/* Bottom Actions */}
      <View style={{ padding: 16, paddingBottom: insets.bottom + 16, backgroundColor: colors.white, borderTopWidth: 2, borderTopColor: colors.border, gap: 8 }}>
        <Button
          title="Confirmar Aluno"
          onPress={handleConfirm}
          size="lg"
          disabled={!selectedStudent}
          icon={<MaterialIcons name="arrow-forward" size={18} color={colors.white} />}
        />
        <Button
          title="Pular (Sem Aluno)"
          onPress={handleSkip}
          variant="outline"
          icon={<MaterialIcons name="skip-next" size={18} color={colors.primary} />}
        />
        <Pressable onPress={() => router.back()} style={{ alignItems: 'center', paddingVertical: 10 }}>
          <Text style={{ fontSize: 14, fontFamily: fonts.bold, color: colors.textSecondary }}>
            Voltar
          </Text>
        </Pressable>
      </View>
    </View>
  );
}
