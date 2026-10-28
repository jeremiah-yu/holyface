import { useState } from 'react';
import {
  KeyboardAvoidingView,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  Text,
  TextInput,
  View,
} from 'react-native';
import { apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { t } from '../i18n';
import { loadStudyContext } from '../storage/studyContext';

type Msg = { role: 'user' | 'bot'; text: string };

export function StudyChatbot() {
  const { token, lang, colors } = useAuth();
  const [open, setOpen] = useState(false);
  const [input, setInput] = useState('');
  const [busy, setBusy] = useState(false);
  const [messages, setMessages] = useState<Msg[]>([{ role: 'bot', text: t(lang, 'chatHint') }]);

  async function send() {
    const text = input.trim();
    if (text.length < 2 || busy) {
      return;
    }
    setInput('');
    setMessages((m) => [...m, { role: 'user', text }]);
    setBusy(true);
    try {
      const latest = await loadStudyContext();
      const notes = (latest.notes ?? '').slice(0, 1600);
      const res = await apiRequest<{ result: { data: { reply: string } } }>('/ai/chat', {
        method: 'POST',
        token,
        timeoutMs: 10000,
        body: JSON.stringify({
          message: text,
          subject: latest.subject,
          topic: latest.topic,
          notes,
        }),
      });
      setMessages((m) => [...m, { role: 'bot', text: res.result.data.reply }]);
    } catch (e: unknown) {
      setMessages((m) => [
        ...m,
        { role: 'bot', text: (e as { message?: string }).message ?? 'Hindi ako makasagot ngayon. Subukan ulit.' },
      ]);
    } finally {
      setBusy(false);
    }
  }

  return (
    <>
      <Pressable
        onPress={() => setOpen(true)}
        accessibilityLabel="Open study chatbot"
        style={{
          position: 'absolute',
          right: 16,
          bottom: 88,
          width: 58,
          height: 58,
          borderRadius: 29,
          backgroundColor: '#c9a227',
          alignItems: 'center',
          justifyContent: 'center',
          elevation: 6,
          shadowColor: '#000',
          shadowOpacity: 0.25,
          shadowRadius: 8,
          zIndex: 50,
        }}
      >
        <Text style={{ color: '#0b1f3a', fontWeight: '800', fontSize: 13 }}>AI</Text>
      </Pressable>

      <Modal visible={open} animationType="slide" transparent onRequestClose={() => setOpen(false)}>
        <KeyboardAvoidingView
          style={{ flex: 1, justifyContent: 'flex-end', backgroundColor: 'rgba(11,31,58,0.35)' }}
          behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        >
          <Pressable style={{ flex: 1 }} onPress={() => setOpen(false)} />
          <View
            style={{
              backgroundColor: colors.bg,
              borderTopLeftRadius: 20,
              borderTopRightRadius: 20,
              maxHeight: '78%',
              minHeight: 420,
              paddingBottom: 10,
            }}
          >
            <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', padding: 16 }}>
              <Text style={{ color: colors.text, fontSize: 18, fontWeight: '800' }}>{t(lang, 'chat')}</Text>
              <Pressable onPress={() => setOpen(false)}>
                <Text style={{ color: '#c9a227', fontWeight: '700' }}>Close</Text>
              </Pressable>
            </View>
            <ScrollView contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 12 }}>
              {messages.map((msg, i) => (
                <View
                  key={`${msg.role}-${i}`}
                  style={{
                    alignSelf: msg.role === 'user' ? 'flex-end' : 'flex-start',
                    backgroundColor: msg.role === 'user' ? '#0b1f3a' : colors.card,
                    padding: 12,
                    borderRadius: 12,
                    marginTop: 8,
                    maxWidth: '90%',
                  }}
                >
                  <Text style={{ color: msg.role === 'user' ? '#fff' : colors.text }}>{msg.text}</Text>
                </View>
              ))}
              {busy ? <Text style={{ color: colors.muted, marginTop: 10 }}>Thinking…</Text> : null}
            </ScrollView>
            <View style={{ flexDirection: 'row', gap: 8, paddingHorizontal: 16, paddingTop: 8 }}>
              <TextInput
                value={input}
                onChangeText={setInput}
                placeholder="Ask a study question"
                placeholderTextColor={colors.muted}
                style={{
                  flex: 1,
                  borderWidth: 1,
                  borderColor: colors.border,
                  backgroundColor: colors.card,
                  color: colors.text,
                  padding: 12,
                  borderRadius: 10,
                }}
              />
              <Pressable
                onPress={send}
                style={{ backgroundColor: '#c9a227', paddingHorizontal: 16, borderRadius: 10, justifyContent: 'center' }}
              >
                <Text style={{ color: '#0b1f3a', fontWeight: '700' }}>{t(lang, 'send')}</Text>
              </Pressable>
            </View>
          </View>
        </KeyboardAvoidingView>
      </Modal>
    </>
  );
}
