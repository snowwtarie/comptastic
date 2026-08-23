export const TODAY_ISO = '2026-08-06';
export const TODAY = new Date(`${TODAY_ISO}T00:00:00`);

export function eur(n, decimals = 2) {
  return (n || 0).toLocaleString('fr-FR', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  });
}

export function toLocalISO(d) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

export function fmtDateLabel(iso, { short = false } = {}) {
  const d = new Date(`${iso}T00:00:00`);
  return d.toLocaleDateString('fr-FR', short
    ? { day: '2-digit', month: 'short' }
    : { day: '2-digit', month: 'short', year: 'numeric' });
}

function daysInMonth(year, monthIndex) {
  // monthIndex is 0-based; passing monthIndex+1 with day 0 yields the last day of monthIndex.
  return new Date(year, monthIndex + 1, 0).getDate();
}

// Adds `months` to `iso`, clamping the day-of-month to the last day of the
// target month instead of overflowing into the next month (matches
// Carbon's addMonthsNoOverflow on the backend, e.g. Jan 31 + 1 month -> Feb 28).
export function addMonthsISO(iso, months) {
  const [y, m, day] = iso.split('-').map(Number);
  const totalMonths = (m - 1) + months;
  const targetYear = y + Math.floor(totalMonths / 12);
  const targetMonthIndex = ((totalMonths % 12) + 12) % 12;
  const targetDay = Math.min(day, daysInMonth(targetYear, targetMonthIndex));
  return `${targetYear}-${String(targetMonthIndex + 1).padStart(2, '0')}-${String(targetDay).padStart(2, '0')}`;
}

// Adds `years` to `iso`, clamping Feb 29 -> Feb 28 on non-leap target years
// instead of overflowing into March (matches Carbon's addYearsNoOverflow).
export function addYearsISO(iso, years) {
  const [y, m, day] = iso.split('-').map(Number);
  const targetYear = y + years;
  const targetMonthIndex = m - 1;
  const targetDay = Math.min(day, daysInMonth(targetYear, targetMonthIndex));
  return `${targetYear}-${String(targetMonthIndex + 1).padStart(2, '0')}-${String(targetDay).padStart(2, '0')}`;
}

export function addStepISO(iso, freq, steps) {
  if (freq === 'weekly') {
    const d = new Date(`${iso}T00:00:00`);
    d.setDate(d.getDate() + steps * 7);
    return toLocalISO(d);
  }
  if (freq === 'yearly') return addYearsISO(iso, steps);
  return addMonthsISO(iso, steps);
}

export function periodRange(period, now = TODAY) {
  const y = now.getFullYear();
  const m = now.getMonth();
  if (period === 'previous') {
    return { start: toLocalISO(new Date(y, m - 1, 1)), end: toLocalISO(new Date(y, m, 0)) };
  }
  if (period === 'year') {
    return { start: `${y}-01-01`, end: `${y}-12-31` };
  }
  return { start: toLocalISO(new Date(y, m, 1)), end: toLocalISO(new Date(y, m + 1, 0)) };
}

export function monthBoundsISO(now = TODAY) {
  const y = now.getFullYear();
  const m = now.getMonth();
  const start = `${y}-${String(m + 1).padStart(2, '0')}-01`;
  const endDate = new Date(y, m + 1, 0);
  const end = `${y}-${String(m + 1).padStart(2, '0')}-${String(endDate.getDate()).padStart(2, '0')}`;
  return { start, end };
}
