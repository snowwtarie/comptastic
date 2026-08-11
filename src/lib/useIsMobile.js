import { ref, onMounted, onUnmounted } from 'vue';

export function useIsMobile(breakpoint = 720) {
  const query = `(max-width: ${breakpoint}px)`;
  const isMobile = ref(typeof window !== 'undefined' ? window.matchMedia(query).matches : false);
  let mql;
  function handler(e) {
    isMobile.value = e.matches;
  }
  onMounted(() => {
    mql = window.matchMedia(query);
    isMobile.value = mql.matches;
    mql.addEventListener('change', handler);
  });
  onUnmounted(() => {
    if (mql) mql.removeEventListener('change', handler);
  });
  return isMobile;
}
