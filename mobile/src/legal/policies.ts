export const POLICY_FALLBACK_VERSION = '1.1';
export const POLICY_LAST_UPDATED = '27 October 2026';
export const POLICY_DISCLAIMER = '';

export type PolicySection = { id: string; title: string; body: string };
export type PolicyDoc = {
  code: string;
  version: string;
  title: string;
  last_updated: string;
  sections: PolicySection[];
};

export type PolicyBundle = {
  version_bundle: string;
  disclaimer: string;
  documents: PolicyDoc[];
};
