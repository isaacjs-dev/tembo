import React from 'react';
import { View, Text } from 'react-native';
import Svg, { Circle } from 'react-native-svg';
import { colors } from '@/theme/colors';
import { fonts } from '@/theme/typography';

interface ScoreCircleProps {
  score: number;
  maxScore: number;
  size?: number;
}

export function ScoreCircle({ score, maxScore, size = 140 }: ScoreCircleProps) {
  const percentage = maxScore > 0 ? score / maxScore : 0;
  const strokeWidth = 8;
  const radius = (size - strokeWidth) / 2;
  const circumference = 2 * Math.PI * radius;
  const strokeDashoffset = circumference * (1 - percentage);

  const scoreColor = percentage >= 0.7 ? colors.primary : percentage >= 0.5 ? colors.amber : colors.danger;

  return (
    <View style={{ width: size, height: size, alignItems: 'center', justifyContent: 'center' }}>
      <Svg width={size} height={size} style={{ position: 'absolute' }}>
        {/* Background circle */}
        <Circle
          cx={size / 2}
          cy={size / 2}
          r={radius}
          stroke={colors.grayLight}
          strokeWidth={strokeWidth}
          fill="none"
        />
        {/* Progress circle */}
        <Circle
          cx={size / 2}
          cy={size / 2}
          r={radius}
          stroke={scoreColor}
          strokeWidth={strokeWidth}
          fill="none"
          strokeDasharray={`${circumference}`}
          strokeDashoffset={strokeDashoffset}
          strokeLinecap="round"
          transform={`rotate(-90 ${size / 2} ${size / 2})`}
        />
      </Svg>
      <View style={{ alignItems: 'center' }}>
        <Text style={{ fontSize: 36, fontFamily: fonts.extraBold, color: scoreColor }}>
          {score.toFixed(1)}
        </Text>
        <Text style={{ fontSize: 14, fontFamily: fonts.bold, color: colors.gray }}>
          / {maxScore}
        </Text>
      </View>
    </View>
  );
}
