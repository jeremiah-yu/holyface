import { useState } from 'react';
import { Alert, Pressable, Text, TextInput, View } from 'react-native';
import { apiRequest } from '../src/api/client';
import { useAuth } from '../src/auth/AuthContext';

export default function CorrectionScreen() {
  const { token, colors } = useAuth();
  const [field, setField] = useState('guardian_contact');
  const [value, setValue] = useState('');
  const [reason, setReason] = useState('');

  return (
    <View style={{ flex: 1, backgroundColor: colors.bg, padding: 16 }}>
      <Text style={{ color: colors.text, fontWeight: '700' }}>Request data correction</Text>
      <Text style={{ color: colors.muted, marginVertical: 8 }}>The Registrar reviews this. Do not include other students’ data.</Text>
      <TextInput value={field} onChangeText={setField} style={inp(colors)} placeholder="Field (full_name, guardian_name, …)" />
      <TextInput value={value} onChangeText={setValue} style={inp(colors)} placeholder="Correct value" />
      <TextInput value={reason} onChangeText={setReason} style={inp(colors)} placeholder="Reason" multiline />
      <Pressable
        style={{ backgroundColor: '#0b1f3a', padding: 14, borderRadius: 12, marginTop: 16 }}
        onPress={() =>
          apiRequest('/students/data-correction', {
            method: 'POST',
            token,
            body: JSON.stringify({ field_name: field, requested_value: value, reason }),
          })
            .then(() => Alert.alert('Sent', 'The Registrar will review your request.'))
            .catch((e) => Alert.alert('Error', e.message))
        }
      >
        <Text style={{ color: '#c9a227', textAlign: 'center', fontWeight: '700' }}>Submit</Text>
      </Pressable>
    </View>
  );
}

function inp(colors: { border: string; card: string; text: string }) {
  return {
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.card,
    color: colors.text,
    padding: 12,
    borderRadius: 8,
    marginTop: 8,
  };
}
