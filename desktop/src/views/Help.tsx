export default function Help() {
  return (
    <div className="card help-card">
      <h2>How grades are computed</h2>
      <p>
        Encode scores in this teacher workspace. Rules follow <strong>DepEd Order No. 8, s. 2015</strong> (Policy Guidelines on Classroom Assessment). Weights
        and the transmutation table are stored in the database so the Registrar can change them without a software
        update. Work Immersion / Research / performance-heavy weights stay unconfirmed until the school sets them.
      </p>

      <h3>1. Percentage Score (each component)</h3>
      <p>
        <code>Percentage Score = (total score ÷ total highest possible score) × 100</code>
      </p>
      <p>This is the SUM method — not the average of each item’s percent. Absent and excused entries are skipped.</p>

      <h3>2–3. Weighted scores and Initial Grade</h3>
      <p>
        <code>Weighted Score = Percentage Score × component weight</code>
        <br />
        <code>Initial Grade = WW weighted + PT weighted + QA weighted</code>
      </p>

      <h3>4. Quarterly Grade</h3>
      <p>Initial Grade is converted using the school’s transmutation table (default: DepEd Appendix 2 bands).</p>

      <h3>5. Final Grade</h3>
      <p>
        <strong>Grade 12</strong> still uses two semesters: Sem 1 = average of Q1 and Q2; Sem 2 = average of Q3 and Q4;
        Final = average of the two semester grades, rounded.
      </p>
      <p>
        <strong>Grade 11 (MATATAG)</strong> uses three terms. Final = average of Term 1, Term 2, and Term 3, rounded.
        There is no semester column for Grade 11.
      </p>
      <h3>Official cards</h3>
      <p>
        Subject teachers submit finals. The <strong>class adviser</strong> generates the cards and releases them at PTC.
        The Registrar keeps the final records and does not issue cards. Students see a released card on the mobile app
        (including modular students who cannot come to school).
      </p>

      <h3>6. General Average</h3>
      <p>Average of final grades across subjects. Passed / Failed uses the stored passing mark (default 75).</p>

      <h3>Worked example</h3>
      <p>Mathematics class using 25% Written Work, 45% Performance Task, 30% Quarterly Assessment:</p>
      <table>
        <thead>
          <tr>
            <th>Component</th>
            <th>Items</th>
            <th>Percentage Score</th>
            <th>Weighted</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Written Work</td>
            <td>36/50 + 40/50 = 76/100</td>
            <td>76.00</td>
            <td>76 × 0.25 = 19.00</td>
          </tr>
          <tr>
            <td>Performance Task</td>
            <td>72/100</td>
            <td>72.00</td>
            <td>72 × 0.45 = 32.40</td>
          </tr>
          <tr>
            <td>Quarterly Assessment</td>
            <td>37/50</td>
            <td>74.00</td>
            <td>74 × 0.30 = 22.20</td>
          </tr>
        </tbody>
      </table>
      <p>
        <strong>Initial Grade</strong> = 19.00 + 32.40 + 22.20 = <strong>73.60</strong>
        <br />
        Look up 73.60 on the transmutation table (72.80–74.39 → 83). <strong>Quarterly Grade = 83</strong>.
      </p>
      <p>
        If Q1 = 83 and Q2 = 85, semester final = round((83 + 85) / 2) = <strong>84</strong>.
      </p>
      <p className="hint">
        Keyboard: arrows move · Tab / Enter · A = absent · E = excused · Ctrl+V paste from Excel. Scores cannot exceed
        the highest possible score. Locked periods need Registrar approval.
      </p>
    </div>
  );
}
