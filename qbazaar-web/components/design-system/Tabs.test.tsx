import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';

import { Tab, TabList, TabPanel, Tabs } from './Tabs';

function NotificationTabs() {
  return (
    <Tabs defaultValue="all">
      <TabList aria-label="Filter notifications">
        <Tab value="all">All</Tab>
        <Tab value="unread">Unread</Tab>
      </TabList>
      <TabPanel value="all">Every notification</TabPanel>
      <TabPanel value="unread">Unread notifications</TabPanel>
    </Tabs>
  );
}

describe('Tabs', () => {
  it('renders an accessible tablist with the default tab selected', () => {
    render(<NotificationTabs />);

    expect(screen.getByRole('tablist', { name: 'Filter notifications' })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: 'All' })).toHaveAttribute('aria-selected', 'true');
    expect(screen.getByRole('tabpanel')).toHaveTextContent('Every notification');
  });

  it('switches the panel when another tab is chosen', async () => {
    const user = userEvent.setup();
    render(<NotificationTabs />);

    await user.click(screen.getByRole('tab', { name: 'Unread' }));

    expect(screen.getByRole('tab', { name: 'Unread' })).toHaveAttribute('aria-selected', 'true');
    expect(screen.getByRole('tabpanel')).toHaveTextContent('Unread notifications');
  });

  it('styles the active pill from the data-active state', () => {
    render(<NotificationTabs />);

    expect(screen.getByRole('tab', { name: 'All' })).toHaveClass('rounded-qb-pill', 'data-active:bg-qb-brand');
  });
});
