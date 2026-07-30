const CACHE_NAME = 'fleetcore-v2';
const STATIC_ASSETS = [
  '/',
  '/manifest.json',
  'https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap',
];

// Instala e faz cache dos assets estáticos
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      return cache.addAll(STATIC_ASSETS).catch(err => {
        console.warn('Cache parcial:', err);
      });
    })
  );
  self.skipWaiting();
});

// Ativa e limpa caches antigos
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
    )
  );
  self.clients.claim();
});

// Estratégia: Network First para API, Cache First para assets estáticos
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // Requisições de API — sempre rede, sem cache
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(
      fetch(event.request).catch(() =>
        new Response(JSON.stringify({ error: 'Sem conexão com o servidor.' }), {
          status: 503,
          headers: { 'Content-Type': 'application/json' },
        })
      )
    );
    return;
  }

  // Assets estáticos — cache primeiro, rede como fallback
  event.respondWith(
    caches.match(event.request).then(cached => {
      if (cached) return cached;
      return fetch(event.request).then(response => {
        // Só faz cache de respostas válidas
        if (response && response.status === 200 && response.type !== 'opaque') {
          const clone = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
        }
        return response;
      }).catch(() => {
        // Offline fallback: retorna a shell do app a partir do cache
        if (event.request.mode === 'navigate') {
          return caches.match('/');
        }
      });
    })
  );
});

// Recebe mensagem para forçar atualização do cache
self.addEventListener('message', event => {
  if (event.data === 'SKIP_WAITING') self.skipWaiting();
});

// Reforço de reenvio dos pontos de GPS pendentes quando o SO permitir
// (Background Sync API — suportado em Chrome/Android; ignorado graciosamente
// em navegadores sem suporte, ex. Safari/iOS). A fila real fica em IndexedDB,
// mantida pelo app (useRastreamento.js); aqui só avisamos os clients abertos
// para tentarem esvaziá-la assim que houver oportunidade.
self.addEventListener('sync', event => {
  if (event.tag === 'sync-viagem-pontos') {
    event.waitUntil(
      self.clients.matchAll({ includeUncontrolled: true }).then(clients => {
        clients.forEach(client => client.postMessage('FLUSH_PONTOS_PENDENTES'));
      })
    );
  }
});
