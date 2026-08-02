import React, { useState } from 'react';
import {
  View,
  Text,
  TextInput,
  Pressable,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  Alert,
  Linking,
} from 'react-native';
import Constants from 'expo-constants';
import { MaterialIcons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAuthStore } from '@/store/auth-store';
import { authService } from '@/services/auth';
import { Button } from '@/components/ui/Button';
import { TemboLogo } from '@/components/ui/TemboLogo';
import { colors } from '@/theme/colors';
import { fonts } from '@/theme/typography';
import { getApiErrorMessage } from '@/lib/api-error';

const WEB_BASE_URL = String(
  Constants.expoConfig?.extra?.webBaseUrl || 'https://tembo.aracruz.org'
).replace(/\/+$/, '');

export default function LoginScreen() {
  const insets = useSafeAreaInsets();
  const { setAuth } = useAuthStore();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);

  const handleLogin = async () => {
    if (!email.trim() || !password.trim()) {
      Alert.alert('Erro', 'Preencha e-mail e senha.');
      return;
    }

    setLoading(true);
    try {
      const response = await authService.login({
        email: email.trim(),
        password,
        device_name: `TemboScanner_${Platform.OS}`,
      });
      await setAuth(response.token, response.user);
    } catch (error: unknown) {
      Alert.alert(
        'Não foi possível entrar',
        getApiErrorMessage(error, 'Verifique suas credenciais e tente novamente.')
      );
    } finally {
      setLoading(false);
    }
  };

  const openWebPath = async (path: string) => {
    const url = `${WEB_BASE_URL}${path}`;
    try {
      await Linking.openURL(url);
    } catch {
      Alert.alert('Não foi possível abrir o link', url);
    }
  };

  return (
    <KeyboardAvoidingView
      style={{ flex: 1, backgroundColor: colors.background }}
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
    >
      <ScrollView
        contentContainerStyle={{
          flexGrow: 1,
          paddingTop: insets.top + 32,
          paddingBottom: insets.bottom + 32,
          paddingHorizontal: 24,
        }}
        keyboardShouldPersistTaps="handled"
      >
        {/* Logo */}
        <View style={{ alignItems: 'center', marginBottom: 40 }}>
          <View
            style={{
              width: 80,
              height: 80,
              backgroundColor: colors.loginOrange,
              borderRadius: 20,
              alignItems: 'center',
              justifyContent: 'center',
              marginBottom: 16,
              shadowColor: colors.loginOrangeDark,
              shadowOffset: { width: 0, height: 4 },
              shadowOpacity: 1,
              shadowRadius: 0,
              elevation: 4,
            }}
          >
            <TemboLogo size={80} />
          </View>
          <Text style={{ fontSize: 28, fontFamily: fonts.extraBold, color: colors.textPrimary, letterSpacing: -0.5 }}>
            Tembo Scanner
          </Text>
          <Text style={{ fontSize: 14, fontFamily: fonts.medium, color: colors.textSecondary, marginTop: 4 }}>
            Digitalize provas num piscar de olhos!
          </Text>
        </View>

        {/* Title */}
        <View style={{ marginBottom: 32 }}>
          <Text style={{ fontSize: 24, fontFamily: fonts.bold, color: colors.textPrimary }}>
            Entrar
          </Text>
          <Text style={{ fontSize: 14, fontFamily: fonts.medium, color: colors.textSecondary, marginTop: 4 }}>
            Boas-vindas ao Tembo!
          </Text>
        </View>

        {/* Email */}
        <View style={{ marginBottom: 20 }}>
          <Text style={{ fontSize: 12, fontFamily: fonts.bold, color: colors.textSecondary, textTransform: 'uppercase', letterSpacing: 1, marginBottom: 8, marginLeft: 4 }}>
            E-mail
          </Text>
          <View style={{ flexDirection: 'row', alignItems: 'center', backgroundColor: colors.white, borderWidth: 2, borderColor: colors.border, borderRadius: 12, paddingHorizontal: 16 }}>
            <MaterialIcons name="mail" size={20} color={colors.gray} style={{ marginRight: 12 }} />
            <TextInput
              style={{ flex: 1, height: 52, fontSize: 15, fontFamily: fonts.medium, color: colors.textPrimary }}
              placeholder="seu@email.com"
              placeholderTextColor={colors.gray}
              value={email}
              onChangeText={setEmail}
              keyboardType="email-address"
              autoComplete="email"
              textContentType="emailAddress"
              autoCapitalize="none"
              autoCorrect={false}
              returnKeyType="next"
              accessibilityLabel="E-mail"
            />
          </View>
        </View>

        {/* Password */}
        <View style={{ marginBottom: 32 }}>
          <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8, paddingHorizontal: 4 }}>
            <Text style={{ fontSize: 12, fontFamily: fonts.bold, color: colors.textSecondary, textTransform: 'uppercase', letterSpacing: 1 }}>
              Senha
            </Text>
            <Pressable
              onPress={() => void openWebPath('/forgot-password')}
              hitSlop={8}
              accessibilityRole="link"
            >
              <Text style={{ fontSize: 12, fontFamily: fonts.bold, color: colors.loginOrange }}>
                Esqueci minha senha
              </Text>
            </Pressable>
          </View>
          <View style={{ flexDirection: 'row', alignItems: 'center', backgroundColor: colors.white, borderWidth: 2, borderColor: colors.border, borderRadius: 12, paddingHorizontal: 16 }}>
            <MaterialIcons name="lock" size={20} color={colors.gray} style={{ marginRight: 12 }} />
            <TextInput
              style={{ flex: 1, height: 52, fontSize: 15, fontFamily: fonts.medium, color: colors.textPrimary }}
              placeholder="Sua senha"
              placeholderTextColor={colors.gray}
              value={password}
              onChangeText={setPassword}
              secureTextEntry={!showPassword}
              autoComplete="current-password"
              textContentType="password"
              returnKeyType="done"
              onSubmitEditing={() => void handleLogin()}
              accessibilityLabel="Senha"
            />
            <Pressable
              onPress={() => setShowPassword(!showPassword)}
              hitSlop={10}
              accessibilityRole="button"
              accessibilityLabel={showPassword ? 'Ocultar senha' : 'Mostrar senha'}
            >
              <MaterialIcons
                name={showPassword ? 'visibility-off' : 'visibility'}
                size={22}
                color={colors.gray}
              />
            </Pressable>
          </View>
        </View>

        {/* Login Button */}
        <Button
          title="Entrar"
          onPress={handleLogin}
          variant="login"
          size="lg"
          loading={loading}
        />

        {/* Tip */}
        <View
          style={{
            marginTop: 32,
            backgroundColor: colors.loginOrangeLight,
            borderWidth: 2,
            borderColor: colors.loginOrange + '30',
            borderRadius: 16,
            padding: 16,
            flexDirection: 'row',
            gap: 12,
          }}
        >
          <View style={{ backgroundColor: colors.loginOrange + '20', padding: 8, borderRadius: 8 }}>
            <MaterialIcons name="lightbulb" size={20} color={colors.loginOrange} />
          </View>
          <View style={{ flex: 1 }}>
            <Text style={{ fontSize: 12, fontFamily: fonts.bold, color: colors.loginOrange, textTransform: 'uppercase', marginBottom: 4 }}>
              Dica Tembo
            </Text>
            <Text style={{ fontSize: 13, fontFamily: fonts.medium, color: colors.textPrimary, lineHeight: 20 }}>
              Para digitalizar suas provas, precisaremos de acesso a sua camera em breve. Prepare o seu material!
            </Text>
          </View>
        </View>

        {/* Footer */}
        <View style={{ marginTop: 'auto', paddingTop: 24, borderTopWidth: 2, borderTopColor: colors.grayLight, alignItems: 'center', justifyContent: 'center', flexDirection: 'row' }}>
          <Text style={{ fontSize: 14, fontFamily: fonts.medium, color: colors.textSecondary }}>
            Não tem uma conta?{' '}
          </Text>
          <Pressable
            onPress={() => void openWebPath('/register')}
            hitSlop={8}
            accessibilityRole="link"
          >
            <Text style={{ color: colors.loginOrange, fontFamily: fonts.bold }}>Cadastre-se</Text>
          </Pressable>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}
