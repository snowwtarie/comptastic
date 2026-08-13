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

export function addMonthsISO(iso, months) {
  const d = new Date(`${iso}T00:00:00`);
  d.setMonth(d.getMonth() + months);
  return toLocalISO(d);
}

export function addStepISO(iso, freq, steps) {
  const d = new Date(`${iso}T00:00:00`);
  if (freq === 'weekly') d.setDate(d.getDate() + steps * 7);
  else if (freq === 'yearly') d.setFullYear(d.getFullYear() + steps);
  else d.setMonth(d.getMonth() + steps);
  return toLocalISO(d);
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
