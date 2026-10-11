import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { SettingsList, SettingsPanel, SettingsRow } from './SettingsPanel';

describe('SettingsPanel', () => {
  it('titles the section and lists its rows', () => {
    render(
      <SettingsPanel title="Account Settings" description="Edit your account settings.">
        <SettingsList>
          <SettingsRow label="Email" value="farah@example.qa" action={<button type="button">Edit</button>} />
          <SettingsRow value="Show phone number" description="Visitors see it on your ads." />
        </SettingsList>
      </SettingsPanel>,
    );

    expect(screen.getByRole('heading', { level: 2, name: 'Account Settings' })).toBeInTheDocument();
    expect(screen.getByText('Edit your account settings.')).toBeInTheDocument();

    const rows = screen.getAllByRole('listitem');
    expect(rows).toHaveLength(2);
    expect(rows[0]).toHaveTextContent('Emailfarah@example.qaEdit');
    expect(rows[1]).toHaveTextContent('Show phone numberVisitors see it on your ads.');
  });

  it('draws each row as its own white r16 card, 20 px apart, at every width', () => {
    render(
      <SettingsList>
        <SettingsRow value="Password" />
      </SettingsList>,
    );

    expect(screen.getByRole('list')).toHaveClass('gap-5');
    expect(screen.getByRole('listitem')).toHaveClass('border', 'rounded-qb-xl', 'bg-qb-surface');
  });
});
