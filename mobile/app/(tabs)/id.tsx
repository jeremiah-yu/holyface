import * as ScreenCapture from 'expo-screen-capture';
import { useCallback, useEffect, useState } from 'react';
import { Image, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import QRCode from 'react-native-qrcode-svg';
import { apiRequest } from '../../src/api/client';
import { useAuth } from '../../src/auth/AuthContext';
import { t } from '../../src/i18n';
import { cacheGet, cacheSet } from '../../src/storage/cache';

type DigitalId = {
  full_name: string;
  lrn: string;
  student_number?: string;
  photo_path: string | null;
  strand_code: string;
  grade_level: number;
  section_code: string;
  school_year: string;
  valid_until: string;
  enrollment_status: string;
  id_active: boolean;
  guardian_name: string | null;
  guardian_contact: string | null;
  qr_token: string;
  school: { name: string; short_name: string; address: string; logo: string };
  generated_at: string;
};

export default function IdScreen() {
  const { token, lang, colors } = useAuth();
  const [card, setCard] = useState<DigitalId | null>(null);
  const [at, setAt] = useState<number | null>(null);
  const [offline, setOffline] = useState(false);
  const [back, setBack] = useState(false);
  const [blockShot, setBlockShot] = useState(false);

  const load = useCallback(async () => {
    const cached = await cacheGet<DigitalId>('digital_id');
    if (cached) {
      setCard(cached.value);
      setAt(cached.at);
    }
    try {
      const res = await apiRequest<{ digital_id: DigitalId }>('/students/digital-id', { token });
      setCard(res.digital_id);
      setAt(Date.now());
      setOffline(false);
      await cacheSet('digital_id', res.digital_id);
    } catch {
      setOffline(true);
    }
  }, [token]);

  useEffect(() => {
    load();
  }, [load]);

  useEffect(() => {
    if (blockShot) {
      ScreenCapture.preventScreenCaptureAsync().catch(() => undefined);
      return () => {
        ScreenCapture.allowScreenCaptureAsync().catch(() => undefined);
      };
    }
    return undefined;
  }, [blockShot]);

  if (!card) {
    return (
      <View style={{ flex: 1, backgroundColor: colors.bg, justifyContent: 'center' }}>
        <Text style={{ textAlign: 'center', color: colors.muted }}>Loading ID…</Text>
      </View>
    );
  }

  const updated = at ? new Date(at).toLocaleString() : '';

  return (
    <ScrollView contentContainerStyle={{ padding: 16, backgroundColor: colors.bg, flexGrow: 1 }}>
      <Pressable onPress={() => setBack(!back)}>
        {!back ? (
          <View style={[styles.card, !card.id_active && styles.inactive]}>
            <Image source={{ uri: card.school.logo }} style={styles.logo} />
            <Text style={styles.school}>{card.school.short_name}</Text>
            <Text style={styles.official}>{card.school.name}</Text>
            <View style={styles.photo} />
            <Text style={styles.name}>{card.full_name}</Text>
            <Text style={styles.meta}>Student number: {card.student_number ?? card.lrn}</Text>
            <Text style={styles.meta}>
              Grade {card.grade_level} · {card.strand_code}-{card.section_code}
            </Text>
            <Text style={styles.meta}>SY {card.school_year}</Text>
            <Text style={styles.meta}>Valid until {card.valid_until}</Text>
            <Text style={[styles.badge, card.id_active ? styles.ok : styles.bad]}>
              {card.id_active ? t(lang, 'enrolled') : t(lang, 'inactive')}
            </Text>
            <Text style={styles.hint}>Tap to flip</Text>
          </View>
        ) : (
          <View style={[styles.card, styles.back]}>
            <Text style={styles.backTitle}>Emergency / Guardian</Text>
            <Text style={styles.backText}>{card.guardian_name ?? '—'}</Text>
            <Text style={styles.backText}>{card.guardian_contact ?? '—'}</Text>
            <Text style={styles.backTitle}>School address</Text>
            <Text style={styles.backText}>{card.school.address}</Text>
            <View style={{ alignItems: 'center', marginTop: 16, backgroundColor: '#fff', padding: 12, borderRadius: 8 }}>
              <QRCode value={card.qr_token} size={160} />
            </View>
            <Text style={[styles.backText, { fontSize: 11, marginTop: 8 }]}>
              QR is a short-lived signed pass — not the raw student number. Staff verify it with the AMGS API.
            </Text>
          </View>
        )}
      </Pressable>
      <Text style={{ color: colors.muted, marginTop: 12 }}>
        {offline ? t(lang, 'offline') : t(lang, 'lastUpdated')}: {updated}
      </Text>
      <Pressable onPress={() => setBlockShot(!blockShot)} style={{ marginTop: 8 }}>
        <Text style={{ color: colors.text }}>Screenshot blocking: {blockShot ? 'ON' : 'OFF (optional)'}</Text>
      </Pressable>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: '#0b1f3a',
    borderRadius: 16,
    padding: 20,
    borderWidth: 3,
    borderColor: '#c9a227',
    minHeight: 420,
  },
  inactive: { opacity: 0.55 },
  back: { backgroundColor: '#123456' },
  logo: { width: 64, height: 64, alignSelf: 'center', borderRadius: 32, backgroundColor: '#fff' },
  school: { color: '#c9a227', textAlign: 'center', fontWeight: '700', marginTop: 8, fontSize: 18 },
  official: { color: '#fff', textAlign: 'center', fontSize: 11, opacity: 0.9 },
  photo: { width: 88, height: 88, borderRadius: 8, backgroundColor: '#1a3a5c', alignSelf: 'center', marginVertical: 12 },
  name: { color: '#fff', fontSize: 22, fontWeight: '700', textAlign: 'center' },
  meta: { color: '#e8edf3', textAlign: 'center', marginTop: 4 },
  badge: { textAlign: 'center', marginTop: 12, fontWeight: '700' },
  ok: { color: '#7dcea0' },
  bad: { color: '#f5a09a' },
  hint: { color: '#c9a227', textAlign: 'center', marginTop: 16 },
  backTitle: { color: '#c9a227', fontWeight: '700', marginTop: 8 },
  backText: { color: '#fff' },
});
