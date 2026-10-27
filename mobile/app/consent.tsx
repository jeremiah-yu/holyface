import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { Alert, NativeScrollEvent, NativeSyntheticEvent, Pressable, ScrollView, Text, TextInput, View } from 'react-native';
import { apiRequest } from '../src/api/client';
import { useAuth } from '../src/auth/AuthContext';
import { t } from '../src/i18n';
import { POLICY_DISCLAIMER, POLICY_FALLBACK_VERSION, POLICY_LAST_UPDATED, PolicyBundle, PolicyDoc } from '../src/legal/policies';

export default function ConsentScreen() {
  const { token, setConsentComplete, refreshMe, lang, colors, user } = useAuth();
  const [bundle, setBundle] = useState<PolicyBundle | null>(null);
  const [needsGuardian, setNeedsGuardian] = useState(true);
  const [scrolled, setScrolled] = useState(false);
  const [accepted, setAccepted] = useState(false);
  const [guardian, setGuardian] = useState('');
  const [rel, setRel] = useState('parent/guardian');
  const [confirmed, setConfirmed] = useState(false);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    apiRequest<PolicyBundle>('/legal/policies', { token })
      .then(setBundle)
      .catch(() =>
        setBundle({
          version_bundle: POLICY_FALLBACK_VERSION,
          disclaimer: POLICY_DISCLAIMER,
          documents: [],
        })
      );
    apiRequest<{ requires_guardian: boolean }>('/legal/status', { token })
      .then((s) => setNeedsGuardian(s.requires_guardian))
      .catch(() => setNeedsGuardian(user?.role === 'student'));
  }, [token, user?.role]);

  function onScroll(e: NativeSyntheticEvent<NativeScrollEvent>) {
    const { layoutMeasurement, contentOffset, contentSize } = e.nativeEvent;
    if (layoutMeasurement.height + contentOffset.y >= contentSize.height - 48) {
      setScrolled(true);
    }
  }

  async function submit() {
    if (!scrolled || !accepted) {
      Alert.alert('Consent', 'Please scroll to the end of the policies and confirm you agree.');
      return;
    }
    if (needsGuardian && (guardian.trim().length < 2 || !confirmed)) {
      Alert.alert('Consent', 'Parent or guardian name and confirmation are required for users under 18.');
      return;
    }
    setBusy(true);
    try {
      await apiRequest('/legal/accept', {
        method: 'POST',
        token,
        body: JSON.stringify({
          scrolled: true,
          accepted: true,
          guardian_name: guardian.trim(),
          relationship: rel.trim() || 'parent/guardian',
          guardian_confirmed: confirmed,
        }),
      });
      setConsentComplete(true);
      await refreshMe();
      router.replace('/');
    } catch (e: unknown) {
      Alert.alert('Consent', (e as { message?: string }).message ?? 'Failed');
    } finally {
      setBusy(false);
    }
  }

  const version = bundle?.version_bundle ?? POLICY_FALLBACK_VERSION;

  return (
    <ScrollView contentContainerStyle={{ padding: 20, backgroundColor: colors.bg }} onScroll={onScroll} scrollEventThrottle={16}>
      <Text style={{ color: colors.text, fontSize: 22, fontWeight: '700' }}>{t(lang, 'consentTitle')}</Text>
      <Text style={{ color: colors.muted, marginVertical: 8 }}>
        Version {version} · Last updated: {POLICY_LAST_UPDATED}
      </Text>
      {(bundle?.disclaimer ?? POLICY_DISCLAIMER).trim() ? (
        <Text style={{ color: colors.text, backgroundColor: colors.card, padding: 10, borderRadius: 8 }}>
          {bundle?.disclaimer ?? POLICY_DISCLAIMER}
        </Text>
      ) : null}
      <Text style={{ color: colors.muted, marginTop: 10 }}>
        Scroll to the end, then accept. A parent or guardian must confirm if you are under 18.
      </Text>
      {(bundle?.documents ?? []).map((doc: PolicyDoc) => (
        <View key={doc.code} style={{ marginTop: 16 }}>
          <Text style={{ color: colors.text, fontWeight: '700', fontSize: 16 }}>{doc.title}</Text>
          <Text style={{ color: colors.muted, marginBottom: 6 }}>
            v{doc.version} · Last updated {doc.last_updated}
          </Text>
          {doc.sections.map((s) => (
            <View key={s.id} style={{ marginBottom: 10 }}>
              <Text style={{ color: colors.text, fontWeight: '700' }}>
                {s.id} {s.title}
              </Text>
              <Text style={{ color: colors.muted }}>{s.body}</Text>
            </View>
          ))}
        </View>
      ))}
      <Text style={{ color: colors.text, fontWeight: '700', marginTop: 12 }}>End of policies.</Text>
      <Pressable onPress={() => scrolled && setAccepted(!accepted)} style={{ flexDirection: 'row', gap: 8, marginTop: 16 }}>
        <Text>{accepted ? '☑' : '☐'}</Text>
        <Text style={{ color: colors.text, flex: 1 }}>
          I have read and agree to the Terms of Use, Privacy Policy, and AI Use Policy (v{version}).
          {!scrolled ? ' (Scroll to the end to enable.)' : ''}
        </Text>
      </Pressable>
      {needsGuardian && (
        <>
          <Text style={{ color: colors.text, fontWeight: '700', marginTop: 16 }}>{t(lang, 'guardian')}</Text>
          <TextInput
            placeholder="Parent / guardian full name"
            value={guardian}
            onChangeText={setGuardian}
            style={{ borderWidth: 1, borderColor: colors.border, backgroundColor: colors.card, color: colors.text, padding: 12, borderRadius: 8, marginTop: 8 }}
          />
          <TextInput
            placeholder="Relationship (e.g. mother, father, aunt)"
            value={rel}
            onChangeText={setRel}
            style={{ borderWidth: 1, borderColor: colors.border, backgroundColor: colors.card, color: colors.text, padding: 12, borderRadius: 8, marginTop: 8 }}
          />
          <Pressable onPress={() => setConfirmed(!confirmed)} style={{ flexDirection: 'row', gap: 8, marginTop: 10 }}>
            <Text>{confirmed ? '☑' : '☐'}</Text>
            <Text style={{ color: colors.text, flex: 1 }}>
              I confirm I am the parent/guardian (or I am with them now) and I consent to AMGS processing.
            </Text>
          </Pressable>
        </>
      )}
      <Pressable
        onPress={submit}
        disabled={busy || !scrolled || !accepted}
        style={{ backgroundColor: '#0b1f3a', marginTop: 20, padding: 14, borderRadius: 12, marginBottom: 40, opacity: !scrolled || !accepted ? 0.5 : 1 }}
      >
        <Text style={{ color: '#c9a227', textAlign: 'center', fontWeight: '700' }}>{busy ? '…' : 'Accept and continue'}</Text>
      </Pressable>
    </ScrollView>
  );
}
