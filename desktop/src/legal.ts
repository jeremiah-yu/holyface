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

export const POLICY_DISCLAIMER = '';
