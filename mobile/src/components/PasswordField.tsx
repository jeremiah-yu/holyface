import { useState } from 'react';
import { Pressable, StyleSheet, TextInput, View } from 'react-native';
import Svg, { Path } from 'react-native-svg';

export function PasswordField({
  value,
  onChangeText,
  placeholder,
  autoComplete,
  colors,
}: {
  value: string;
  onChangeText: (v: string) => void;
  placeholder?: string;
  autoComplete?: 'password' | 'new-password' | 'current-password';
  colors: { border: string; card: string; text: string; muted: string };
}) {
  const [show, setShow] = useState(false);
  return (
    <View style={[styles.wrap, { borderColor: colors.border, backgroundColor: colors.card }]}>
      <TextInput
        style={[styles.input, { color: colors.text }]}
        secureTextEntry={!show}
        value={value}
        onChangeText={onChangeText}
        placeholder={placeholder}
        placeholderTextColor={colors.muted}
        autoComplete={autoComplete ?? 'password'}
      />
      <Pressable
        onPress={() => setShow((v) => !v)}
        accessibilityLabel={show ? 'Hide password' : 'Show password'}
        hitSlop={8}
        style={styles.eye}
      >
        <EyeIcon open={show} color="#5a6472" />
      </Pressable>
    </View>
  );
}

function EyeIcon({ open, color }: { open: boolean; color: string }) {
  if (open) {
    return (
      <Svg width={22} height={22} viewBox="0 0 24 24" fill="none">
        <Path d="M3 3l18 18" stroke={color} strokeWidth={1.7} strokeLinecap="round" />
        <Path
          d="M10.6 10.6a2 2 0 002.8 2.8M9.9 5.1A10.5 10.5 0 0112 5c7 0 10 7 10 7a16.8 16.8 0 01-3.2 4.1M6.1 6.1A16.7 16.8 0 002 12s3 7 10 7a10.4 10.4 0 004.2-.9"
          stroke={color}
          strokeWidth={1.7}
          strokeLinecap="round"
          strokeLinejoin="round"
        />
      </Svg>
    );
  }
  return (
    <Svg width={22} height={22} viewBox="0 0 24 24" fill="none">
      <Path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" stroke={color} strokeWidth={1.7} strokeLinejoin="round" />
      <Path d="M12 15a3 3 0 100-6 3 3 0 000 6Z" stroke={color} strokeWidth={1.7} />
    </Svg>
  );
}

const styles = StyleSheet.create({
  wrap: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderRadius: 8,
    marginTop: 8,
    paddingRight: 4,
  },
  input: { flex: 1, paddingVertical: 12, paddingHorizontal: 12 },
  eye: { width: 44, height: 44, alignItems: 'center', justifyContent: 'center' },
});
