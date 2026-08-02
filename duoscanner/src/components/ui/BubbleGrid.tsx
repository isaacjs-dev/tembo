import React from 'react';
import { View, Text, Pressable } from 'react-native';
import { colors } from '@/theme/colors';
import { fonts } from '@/theme/typography';

const LETTERS = ['A', 'B', 'C', 'D', 'E'];

interface BubbleGridProps {
  questionNumber: number;
  optionCount: number;
  selectedOption: number | null;
  correctOption?: number | null;
  confidence?: number;
  showCorrect?: boolean;
  onSelectOption?: (option: number) => void;
  editable?: boolean;
}

export function BubbleGrid({
  questionNumber,
  optionCount,
  selectedOption,
  correctOption,
  confidence = 1,
  showCorrect = false,
  onSelectOption,
  editable = false,
}: BubbleGridProps) {
  const isCorrect = showCorrect && selectedOption === correctOption;
  const isWrong = showCorrect && selectedOption !== null && selectedOption !== correctOption;
  const isLowConfidence = confidence < 0.5;

  return (
    <View
      style={{
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: colors.white,
        borderWidth: 2,
        borderColor: isLowConfidence
          ? colors.amber + '60'
          : isWrong
          ? colors.danger + '20'
          : colors.border,
        borderRadius: 12,
        padding: 12,
        gap: 12,
      }}
    >
      {/* Question number */}
      <View
        style={{
          width: 36,
          height: 36,
          borderRadius: 8,
          backgroundColor: isWrong
            ? colors.danger + '15'
            : isCorrect
            ? colors.primaryLight
            : colors.grayLight,
          alignItems: 'center',
          justifyContent: 'center',
        }}
      >
        <Text
          style={{
            fontSize: 14,
            fontFamily: fonts.extraBold,
            color: isWrong ? colors.danger : isCorrect ? colors.primary : colors.textPrimary,
          }}
        >
          {String(questionNumber).padStart(2, '0')}
        </Text>
      </View>

      {/* Bubbles */}
      <View style={{ flexDirection: 'row', gap: 6, flex: 1 }}>
        {Array.from({ length: optionCount }, (_, i) => {
          const isSelected = selectedOption === i;
          const isCorrectOption = showCorrect && correctOption === i;

          return (
            <Pressable
              key={i}
              onPress={() => editable && onSelectOption?.(i)}
              disabled={!editable}
              style={{
                width: 36,
                height: 36,
                borderRadius: 18,
                borderWidth: 2,
                borderColor: isSelected
                  ? isWrong && !isCorrectOption
                    ? colors.danger
                    : colors.primary
                  : isCorrectOption
                  ? colors.primary
                  : colors.border,
                backgroundColor: isSelected
                  ? isWrong && !isCorrectOption
                    ? colors.danger + '15'
                    : colors.primaryLight
                  : isCorrectOption
                  ? colors.primaryLight
                  : 'transparent',
                alignItems: 'center',
                justifyContent: 'center',
              }}
            >
              <Text
                style={{
                  fontSize: 13,
                  fontFamily: fonts.bold,
                  color: isSelected
                    ? isWrong && !isCorrectOption
                      ? colors.danger
                      : colors.primary
                    : isCorrectOption
                    ? colors.primary
                    : colors.textSecondary,
                }}
              >
                {LETTERS[i]}
              </Text>
            </Pressable>
          );
        })}
      </View>

      {/* Status indicator */}
      {showCorrect && (
        <View
          style={{
            width: 28,
            height: 28,
            borderRadius: 14,
            backgroundColor: isCorrect ? colors.primary : colors.danger,
            alignItems: 'center',
            justifyContent: 'center',
          }}
        >
          <Text style={{ color: colors.white, fontSize: 16, fontFamily: fonts.bold }}>
            {isCorrect ? '\u2713' : '\u2717'}
          </Text>
        </View>
      )}
    </View>
  );
}
