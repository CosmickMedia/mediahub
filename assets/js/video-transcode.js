/**
 * Client-side .mov → .mp4 transcoder using ffmpeg.wasm.
 *
 * We transcode MOV uploads to H.264 MP4 in the browser before sending them
 * to Hootsuite, because:
 *  - HEVC-encoded iPhone clips (the default since iPhone 7) are rejected by
 *    Hootsuite's validator, and the server has no ffmpeg available.
 *  - Even H.264 MOVs sometimes have container quirks that trip Hootsuite.
 *
 * The single-threaded ffmpeg-core is used so we don't require
 * Cross-Origin-Isolated headers. The ~30MB wasm core is lazy-loaded from
 * jsDelivr and cached by the browser after the first use.
 *
 * Public API:
 *   transcodeMovToMp4(file, onProgress) => Promise<File>
 *   createTranscodeOverlay()            => { setProgress, close }
 */

const FFMPEG_VERSION = '0.12.10';
const UTIL_VERSION   = '0.12.2';
const CORE_VERSION   = '0.12.10';
const CDN            = 'https://cdn.jsdelivr.net/npm';

// Mobile Safari can exhaust memory on very large clips. Skip transcoding
// rather than crashing the tab; the server-side MIME normalization may
// still let the original file through.
const MAX_TRANSCODE_SIZE = 200 * 1024 * 1024;

let ffmpegInstance = null;
let ffmpegLoadPromise = null;

function loadScript(src) {
    return new Promise((resolve, reject) => {
        // Avoid re-adding if already present (same-src script returns existing node)
        const existing = document.querySelector('script[data-src="' + src + '"]');
        if (existing && existing.dataset.loaded === '1') { resolve(); return; }
        const s = document.createElement('script');
        s.src = src;
        s.dataset.src = src;
        s.onload = () => { s.dataset.loaded = '1'; resolve(); };
        s.onerror = () => reject(new Error('Failed to load ' + src));
        document.head.appendChild(s);
    });
}

async function getFFmpeg() {
    if (ffmpegInstance) return ffmpegInstance;
    if (ffmpegLoadPromise) return ffmpegLoadPromise;

    ffmpegLoadPromise = (async () => {
        if (typeof WebAssembly === 'undefined') {
            const err = new Error('WebAssembly is not supported in this browser');
            err.name = 'WASM_UNSUPPORTED';
            throw err;
        }

        // Load the UMD bundles which attach globals. We use UMD (not ESM)
        // because jsDelivr's ESM bundles contain bare specifiers which the
        // browser can't resolve without an import map.
        if (!window.FFmpegUtil) {
            await loadScript(`${CDN}/@ffmpeg/util@${UTIL_VERSION}/dist/umd/index.js`);
        }
        if (!window.FFmpegWASM) {
            await loadScript(`${CDN}/@ffmpeg/ffmpeg@${FFMPEG_VERSION}/dist/umd/ffmpeg.js`);
        }

        const { FFmpeg }    = window.FFmpegWASM;
        const { toBlobURL } = window.FFmpegUtil;
        const baseURL       = `${CDN}/@ffmpeg/core@${CORE_VERSION}/dist/umd`;

        const ffmpeg = new FFmpeg();
        // toBlobURL sidesteps cross-origin WASM fetch restrictions.
        await ffmpeg.load({
            coreURL: await toBlobURL(`${baseURL}/ffmpeg-core.js`,   'text/javascript'),
            wasmURL: await toBlobURL(`${baseURL}/ffmpeg-core.wasm`, 'application/wasm'),
        });

        ffmpegInstance = ffmpeg;
        return ffmpeg;
    })();

    try {
        return await ffmpegLoadPromise;
    } catch (err) {
        // Allow a subsequent call to retry after a transient failure.
        ffmpegLoadPromise = null;
        throw err;
    }
}

/**
 * Transcode a MOV File to an H.264 MP4 File.
 * @param {File} file        Original file; should be a MOV.
 * @param {(pct:number, label?:string) => void} [onProgress]
 * @returns {Promise<File>}  New File with .mp4 extension and type 'video/mp4'.
 */
export async function transcodeMovToMp4(file, onProgress) {
    if (file.size > MAX_TRANSCODE_SIZE) {
        const err = new Error('File too large for in-browser transcoding');
        err.name = 'FILE_TOO_LARGE';
        throw err;
    }

    const notify = (pct, label) => {
        if (typeof onProgress === 'function') onProgress(pct, label);
    };

    notify(0, 'Loading converter…');
    const ffmpeg = await getFFmpeg();
    notify(0, 'Starting…');

    const progressHandler = ({ progress }) => {
        // ffmpeg.wasm sometimes reports progress > 1 or < 0; clamp it.
        const pct = Math.max(0, Math.min(100, Math.round((progress || 0) * 100)));
        notify(pct);
    };
    ffmpeg.on('progress', progressHandler);

    const inputName  = 'input.mov';
    const outputName = 'output.mp4';

    try {
        const bytes = new Uint8Array(await file.arrayBuffer());
        await ffmpeg.writeFile(inputName, bytes);

        // ultrafast preset keeps mobile transcode time around 1-2x clip length.
        // +faststart moves the moov atom to the start so the MP4 streams cleanly.
        const exitCode = await ffmpeg.exec([
            '-i', inputName,
            '-c:v', 'libx264',
            '-preset', 'ultrafast',
            '-crf', '23',
            '-c:a', 'aac',
            '-movflags', '+faststart',
            outputName,
        ]);

        if (exitCode !== 0) {
            throw new Error('ffmpeg exited with code ' + exitCode);
        }

        const data = await ffmpeg.readFile(outputName);

        const baseName = (file.name || 'video').replace(/\.[^.]+$/, '') || 'video';
        const mp4Name  = baseName + '.mp4';

        notify(100, 'Done');
        return new File([data.buffer], mp4Name, {
            type: 'video/mp4',
            lastModified: Date.now(),
        });
    } finally {
        try { ffmpeg.off('progress', progressHandler); } catch (e) {}
        // Free WASM memory between runs so multiple clips don't accumulate.
        try { await ffmpeg.deleteFile(inputName);  } catch (e) {}
        try { await ffmpeg.deleteFile(outputName); } catch (e) {}
    }
}

/**
 * Create a dimmed full-screen overlay with a progress bar. Returns a
 * controller with { setProgress(pct, label?), close() }. Safe to call
 * setProgress after close (no-op).
 */
export function createTranscodeOverlay() {
    const overlay = document.createElement('div');
    overlay.className = 'video-transcode-overlay';
    overlay.style.cssText = [
        'position:fixed', 'inset:0',
        'background:rgba(0,0,0,0.55)',
        'z-index:2000',
        'display:flex', 'align-items:center', 'justify-content:center',
        'padding:1rem',
    ].join(';');

    const card = document.createElement('div');
    card.style.cssText = [
        'max-width:400px', 'width:100%',
        'background:#fff', 'border-radius:12px',
        'box-shadow:0 10px 40px rgba(0,0,0,0.25)',
        'padding:1.5rem',
    ].join(';');

    card.innerHTML = `
        <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem;">
            <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
            <div style="flex:1;">
                <div style="font-weight:600;">Preparing video…</div>
                <div style="color:#6c757d;font-size:0.85rem;" data-role="transcode-status">Loading converter…</div>
            </div>
        </div>
        <div class="progress" style="height:8px;border-radius:4px;overflow:hidden;background:#e9ecef;">
            <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" data-role="transcode-bar" role="progressbar" style="width:0%;height:100%;transition:width 0.2s;"></div>
        </div>
        <div style="color:#6c757d;font-size:0.75rem;margin-top:0.75rem;">
            Converting MOV to MP4 in your browser. Please keep this tab open.
        </div>
    `;

    overlay.appendChild(card);
    document.body.appendChild(overlay);

    const bar    = card.querySelector('[data-role="transcode-bar"]');
    const status = card.querySelector('[data-role="transcode-status"]');

    let startedAt = null;
    let closed    = false;

    return {
        setProgress(pct, label) {
            if (closed) return;
            const p = Math.max(0, Math.min(100, pct || 0));
            if (startedAt === null && p > 0) startedAt = Date.now();
            bar.style.width = p + '%';

            if (label) {
                status.textContent = label;
            } else if (startedAt && p >= 5 && p < 100) {
                const elapsed   = (Date.now() - startedAt) / 1000;
                const remaining = Math.max(1, Math.round((elapsed / p) * (100 - p)));
                status.textContent = p + '% — about ' + remaining + 's remaining';
            } else {
                status.textContent = p + '%';
            }
        },
        close() {
            if (closed) return;
            closed = true;
            if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
        },
    };
}
