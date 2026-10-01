// Reduz fotos de celular (normalmente 3–8 MB) para JPEG de até ~1600px,
// evitando estourar o upload_max_filesize do PHP no servidor.
export default async function comprimirImagem(file, { maxLado = 1600, qualidade = 0.8 } = {}) {
  if (!file?.type?.startsWith('image/') || typeof createImageBitmap !== 'function') return file

  try {
    const bitmap = await createImageBitmap(file)
    const escala = Math.min(1, maxLado / Math.max(bitmap.width, bitmap.height))
    const canvas = document.createElement('canvas')
    canvas.width = Math.round(bitmap.width * escala)
    canvas.height = Math.round(bitmap.height * escala)
    canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height)
    bitmap.close?.()

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', qualidade))
    if (!blob) return file

    const nome = file.name.replace(/\.[^.]+$/, '') + '.jpg'
    return new File([blob], nome, { type: 'image/jpeg' })
  } catch {
    return file
  }
}
