const DB_NAME = 'fleetcore-rastreamento'
const STORE = 'pontos_pendentes'

function openDb() {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, 1)
    req.onupgradeneeded = () => {
      req.result.createObjectStore(STORE, { keyPath: 'localId', autoIncrement: true })
    }
    req.onsuccess = () => resolve(req.result)
    req.onerror = () => reject(req.error)
  })
}

async function withStore(mode, fn) {
  const db = await openDb()
  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE, mode)
    const store = tx.objectStore(STORE)
    const result = fn(store)
    tx.oncomplete = () => resolve(result)
    tx.onerror = () => reject(tx.error)
  })
}

export async function enfileirarPonto(viagemId, ponto) {
  await withStore('readwrite', store => store.add({ viagemId, ponto }))
}

export async function listarPendentes() {
  return withStore('readonly', store => {
    return new Promise((resolve, reject) => {
      const items = []
      const req = store.openCursor()
      req.onsuccess = () => {
        const cursor = req.result
        if (cursor) {
          items.push({ localId: cursor.primaryKey, ...cursor.value })
          cursor.continue()
        } else {
          resolve(items)
        }
      }
      req.onerror = () => reject(req.error)
    })
  }).then(v => v)
}

export async function removerPonto(localId) {
  await withStore('readwrite', store => store.delete(localId))
}
