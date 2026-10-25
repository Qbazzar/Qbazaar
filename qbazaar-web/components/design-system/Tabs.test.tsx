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

  it('can scroll the tabs sideways on phones instead of wrapping them', () => {
    render(
      <Tabs defaultValue="all">
        <TabList aria-label="My ads" scrollOnPhones>
          <Tab value="all">All</Tab>
        </TabList>
      </Tabs>,
    );

    expect(screen.getByRole('tablist', { name: 'My ads' })).toHaveClass('max-qb-tablet:flex-nowrap', 'max-qb-tablet:overflow-x-auto');
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

  it('keeps the active tab orange under the pointer', () => {
    render(<NotificationTabs />);

    expect(screen.getByRole('tab', { name: 'All' })).toHaveClass('hover:bg-qb-hover', 'data-active:hover:bg-qb-brand');
  });

  it('draws the flat underline bar of seller.html: no chip, an orange line under the chosen tab', () => {
    render(
      <Tabs defaultValue="ads" variant="underline">
        <TabList aria-label="Seller">
          <Tab value="ads">Ads</Tab>
          <Tab value="reviews">Reviews</Tab>
        </TabList>
        <TabPanel value="ads">Listings</TabPanel>
        <TabPanel value="reviews">Reviews list</TabPanel>
      </Tabs>,
    );

    expect(screen.getByRole('tablist', { name: 'Seller' })).toHaveClass('gap-7', 'border-b', 'border-qb-line');
    const ads = screen.getByRole('tab', { name: 'Ads' });
    expect(ads).toHaveClass('border-b-2', 'border-transparent', 'pb-3.5', 'text-qb-ink-faint', 'data-active:border-qb-brand', 'data-active:text-qb-brand');
    expect(ads).not.toHaveClass('rounded-qb-pill', 'bg-qb-hover');
    expect(ads.className).not.toMatch(/hover:/);
    expect(screen.getByRole('tabpanel')).toHaveClass('mt-[22px]');
  });

  it('moves between underline tabs with the arrow keys', async () => {
    const user = userEvent.setup();
    render(
      <Tabs defaultValue="ads" variant="underline">
        <TabList aria-label="Seller">
          <Tab value="ads">Ads</Tab>
          <Tab value="reviews">Reviews</Tab>
        </TabList>
        <TabPanel value="ads">Listings</TabPanel>
        <TabPanel value="reviews">Reviews list</TabPanel>
      </Tabs>,
    );

    await user.click(screen.getByRole('tab', { name: 'Ads' }));
    await user.keyboard('{ArrowRight}');

    expect(screen.getByRole('tab', { name: 'Reviews' })).toHaveFocus();
  });
});
