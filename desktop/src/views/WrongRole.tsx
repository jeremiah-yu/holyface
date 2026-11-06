const LOGO = 'https://www.holyface.school/assets/img/branding/hf-logo.png';

export default function WrongRole({
  title,
  body,
  onBack,
}: {
  title: string;
  body: string;
  onBack: () => void;
}) {
  return (
    <div className="login-wrap">
      <div className="card login">
        <img src={LOGO} alt="" className="logo" />
        <h2>{title}</h2>
        <p className="hint">{body}</p>
        <button type="button" onClick={onBack}>
          Back to sign in
        </button>
      </div>
    </div>
  );
}
