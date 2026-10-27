import { useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { Alert, Pressable, Text, View } from 'react-native';
import { apiRequest } from '../src/api/client';
import { useAuth } from '../src/auth/AuthContext';
import { t } from '../src/i18n';

type Card = { id?: number; front?: string; back?: string; front_text?: string; back_text?: string };

export default function FlashcardsScreen() {
  const { token, lang, colors } = useAuth();
  const p = useLocalSearchParams<{ subject?: string; topic?: string; notes?: string }>();
  const [cards, setCards] = useState<Card[]>([]);
  const [i, setI] = useState(0);
  const [showBack, setShowBack] = useState(false);
  const [session, setSession] = useState('');

  useEffect(() => {
    (async () => {
      const res = await apiRequest<{
        result: { session_token: string; data: { cards: Card[]; deck_id?: number } };
      }>('/ai/study', {
        method: 'POST',
        token,
        body: JSON.stringify({
          tool_type: 'flashcards',
          subject: p.subject,
          topic: p.topic || p.subject,
          notes: p.notes,
        }),
      });
      setSession(res.result.session_token);
      const deckId = res.result.data.deck_id;
      if (deckId) {
        const list = await apiRequest<{ cards: Card[] }>(`/ai/flashcards/cards?deck_id=${deckId}`, { token });
        setCards(list.cards);
      } else {
        setCards(res.result.data.cards);
      }
    })().catch((e) => Alert.alert('Flashcards', e.message ?? 'Failed'));
  }, [token]);

  const card = cards[i];
  const front = card?.front_text ?? card?.front ?? '';
  const back = card?.back_text ?? card?.back ?? '';

  async function review(result: 'got_it' | 'again') {
    if (card?.id) {
      await apiRequest('/ai/flashcards/review', {
        method: 'POST',
        token,
        body: JSON.stringify({ card_id: card.id, result }),
      }).catch(() => undefined);
    }
    setShowBack(false);
    setI((n) => (n + 1) % Math.max(cards.length, 1));
  }

  if (!card) {
    return (
      <View style={{ flex: 1, backgroundColor: colors.bg, justifyContent: 'center' }}>
        <Text style={{ textAlign: 'center', color: colors.muted }}>Generating…</Text>
      </View>
    );
  }

  return (
    <View style={{ flex: 1, backgroundColor: colors.bg, padding: 16, justifyContent: 'center' }}>
      <Text style={{ color: '#c9a227', marginBottom: 8 }}>{t(lang, 'aiLabel')}</Text>
      <Pressable
        onPress={() => setShowBack(!showBack)}
        style={{ backgroundColor: '#0b1f3a', minHeight: 220, borderRadius: 16, padding: 20, justifyContent: 'center', borderWidth: 3, borderColor: '#c9a227' }}
      >
        <Text style={{ color: '#fff', fontSize: 18, textAlign: 'center' }}>{showBack ? back : front}</Text>
        <Text style={{ color: '#c9a227', textAlign: 'center', marginTop: 16 }}>{showBack ? 'Back' : 'Front'} · tap to flip</Text>
      </Pressable>
      <Text style={{ color: colors.muted, textAlign: 'center', marginTop: 8 }}>
        {i + 1} / {cards.length}
      </Text>
      <View style={{ flexDirection: 'row', gap: 12, marginTop: 16 }}>
        <Pressable onPress={() => review('again')} style={{ flex: 1, backgroundColor: colors.card, padding: 14, borderRadius: 12 }}>
          <Text style={{ textAlign: 'center', color: colors.text }}>{t(lang, 'again')}</Text>
        </Pressable>
        <Pressable onPress={() => review('got_it')} style={{ flex: 1, backgroundColor: '#0b1f3a', padding: 14, borderRadius: 12 }}>
          <Text style={{ textAlign: 'center', color: '#c9a227' }}>{t(lang, 'gotIt')}</Text>
        </Pressable>
      </View>
      <Pressable
        onPress={() =>
          apiRequest('/ai/report', {
            method: 'POST',
            token,
            body: JSON.stringify({ session_token: session, item_ref: String(i), comment: 'Wrong flashcard' }),
          }).then(() => Alert.alert('Thanks'))
        }
      >
        <Text style={{ color: colors.muted, marginTop: 16, textAlign: 'center' }}>{t(lang, 'report')}</Text>
      </Pressable>
    </View>
  );
}
