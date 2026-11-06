export default function HelpRegistrar() {
  return (
    <div className="card help-card">
      <h2>Registrar desktop</h2>
      <p>
        This Windows app is for the Registrar and school administrators. Teachers encode grades in the <strong>web
        teacher workspace</strong>. Students use the mobile app.
      </p>
      <h3>Keeping files</h3>
      <p>
        The file vault is on Overview. Use it for circulars, enrollment forms, policy PDFs, and backup copies. Files
        are stored on the school server (not on this PC). Each file may be up to 15 MB.
      </p>
      <h3>Maintaining the system</h3>
      <ul>
        <li>Create teacher and student accounts. New users must change the temporary password at first login.</li>
        <li>Deactivate accounts that should no longer sign in.</li>
        <li>Lock a grading period so teachers cannot change scores after the deadline. Period 1–3 apply to both Grade 11 terms and Grade 12 quarters; Period 4 is Grade 12 Q4 only.</li>
        <li>Official cards are generated and released by the class adviser, not from this desktop. This app keeps the archive, accounts, and locks.</li>
        <li>Confirm DepEd component weights and the passing mark before a school year starts.</li>
      </ul>
      <h3>Privacy (RA 10173)</h3>
      <p>
        Learner names, student numbers, photos, and grades stay on school systems. Do not copy official records onto
        personal USB drives or email them to personal accounts.
      </p>
    </div>
  );
}
