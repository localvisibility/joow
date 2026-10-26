// Enregistreur d'écran via CDP (frames JPEG à la résolution réelle + timestamps) → ffmpeg.
import fs from 'node:fs';
import path from 'node:path';

export async function startRecorder(page, dir, { maxWidth = 1200, maxHeight = 2600, quality = 88 } = {}) {
  fs.mkdirSync(dir, { recursive: true });
  const cdp = await page.context().newCDPSession(page);
  const frames = [];
  let n = 0;
  cdp.on('Page.screencastFrame', async ({ data, metadata, sessionId }) => {
    const f = path.join(dir, `f${String(n++).padStart(5, '0')}.jpg`);
    fs.writeFileSync(f, Buffer.from(data, 'base64'));
    frames.push({ f, t: metadata.timestamp });
    try { await cdp.send('Page.screencastFrameAck', { sessionId }); } catch {}
  });
  await cdp.send('Page.startScreencast', { format: 'jpeg', quality, maxWidth, maxHeight, everyNthFrame: 1 });
  return {
    async stop() {
      try { await cdp.send('Page.stopScreencast'); } catch {}
      await new Promise((r) => setTimeout(r, 300));
      // fichier concat ffmpeg avec durées réelles
      const lines = [];
      for (let i = 0; i < frames.length; i++) {
        const d = i < frames.length - 1 ? Math.max(0.016, frames[i + 1].t - frames[i].t) : 0.1;
        lines.push(`file '${path.basename(frames[i].f)}'`, `duration ${d.toFixed(4)}`);
      }
      if (frames.length) lines.push(`file '${path.basename(frames[frames.length - 1].f)}'`);
      fs.writeFileSync(path.join(dir, 'list.txt'), lines.join('\n'));
      return { frames: frames.length, seconds: frames.length ? frames[frames.length - 1].t - frames[0].t : 0 };
    },
    mark(label) { frames.push({ f: frames.at(-1)?.f, t: frames.at(-1)?.t, label }); },
  };
}

export const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
