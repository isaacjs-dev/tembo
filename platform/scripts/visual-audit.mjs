import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';

const [
    email = 'student@email.com',
    password = 'password',
    targetPath = '/student/dashboard',
    outputPath = 'storage/app/qa-page.png',
    widthArg = '1440',
    heightArg = '1000',
] = process.argv.slice(2);

const width = Number(widthArg);
const height = Number(heightArg);
const origin = 'http://127.0.0.1:8000';
const debuggerOrigin = 'http://127.0.0.1:9223';

const targets = await fetch(`${debuggerOrigin}/json/list`).then((response) => response.json());
const target = targets.find((item) => item.type === 'page');

if (!target?.webSocketDebuggerUrl) {
    throw new Error('Nenhuma página do Chrome disponível na porta 9223.');
}

const socket = new WebSocket(target.webSocketDebuggerUrl);
const pending = new Map();
const eventWaiters = new Map();
let sequence = 0;

socket.addEventListener('message', ({ data }) => {
    const message = JSON.parse(data);

    if (message.id && pending.has(message.id)) {
        const { resolve: resolveCall, reject } = pending.get(message.id);
        pending.delete(message.id);
        if (message.error) {
            reject(new Error(message.error.message));
        } else {
            resolveCall(message.result);
        }
        return;
    }

    const waiters = eventWaiters.get(message.method) ?? [];
    eventWaiters.delete(message.method);
    waiters.forEach((resolveEvent) => resolveEvent(message.params));
});

await new Promise((resolveSocket, rejectSocket) => {
    socket.addEventListener('open', resolveSocket, { once: true });
    socket.addEventListener('error', rejectSocket, { once: true });
});

function call(method, params = {}) {
    const id = ++sequence;
    socket.send(JSON.stringify({ id, method, params }));

    return new Promise((resolveCall, reject) => {
        pending.set(id, { resolve: resolveCall, reject });
    });
}

function waitForEvent(method, timeout = 10_000) {
    return new Promise((resolveEvent, reject) => {
        const timer = setTimeout(() => reject(new Error(`Timeout aguardando ${method}`)), timeout);
        const wrappedResolve = (params) => {
            clearTimeout(timer);
            resolveEvent(params);
        };
        const waiters = eventWaiters.get(method) ?? [];
        waiters.push(wrappedResolve);
        eventWaiters.set(method, waiters);
    });
}

async function navigate(url) {
    const loaded = waitForEvent('Page.loadEventFired');
    await call('Page.navigate', { url });
    await loaded;
    await new Promise((resolveWait) => setTimeout(resolveWait, 500));
}

await call('Page.enable');
await call('Runtime.enable');
await call('Network.enable');
await call('Network.clearBrowserCookies');
await call('Storage.clearDataForOrigin', {
    origin,
    storageTypes: 'local_storage,session_storage',
});
await call('Emulation.setDeviceMetricsOverride', {
    width,
    height,
    deviceScaleFactor: 1,
    mobile: width < 600,
    screenWidth: width,
    screenHeight: height,
});

await navigate(`${origin}/login`);

const loginLoaded = waitForEvent('Page.loadEventFired');
const loginResult = await call('Runtime.evaluate', {
    expression: `(() => {
        const email = document.querySelector('input[name="email"]');
        const password = document.querySelector('input[name="password"]');
        const form = email?.closest('form');
        if (!email || !password || !form) return { ok: false, reason: 'form-not-found' };
        email.value = ${JSON.stringify(email)};
        password.value = ${JSON.stringify(password)};
        form.requestSubmit();
        return { ok: true };
    })()`,
    returnByValue: true,
});

if (!loginResult.result?.value?.ok) {
    throw new Error(`Falha ao preencher login: ${JSON.stringify(loginResult.result?.value)}`);
}

await loginLoaded;
await navigate(`${origin}${targetPath}`);

const finalLocation = await call('Runtime.evaluate', {
    expression: 'location.href',
    returnByValue: true,
});

if (new URL(finalLocation.result.value).pathname === '/login') {
    throw new Error(`A autenticação não persistiu ao abrir ${targetPath}. Verifique a conta e a base de QA.`);
}

const pageState = await call('Runtime.evaluate', {
    expression: `(() => {
        const normalize = (value) => (value || '').replace(/\\s+/g, ' ').trim();
        const selector = (element) => {
            if (element.id) return '#' + CSS.escape(element.id);
            const name = element.getAttribute('name');
            if (name) return element.tagName.toLowerCase() + '[name="' + CSS.escape(name) + '"]';
            const classes = [...element.classList].slice(0, 2).map((item) => '.' + CSS.escape(item)).join('');
            return element.tagName.toLowerCase() + classes;
        };
        const accessibleName = (element, includeContent = true) => {
            const labelledBy = element.getAttribute('aria-labelledby');
            if (labelledBy) {
                const value = labelledBy.split(/\\s+/).map((id) => document.getElementById(id)?.textContent).join(' ');
                if (normalize(value)) return normalize(value);
            }
            const ariaLabel = normalize(element.getAttribute('aria-label'));
            if (ariaLabel) return ariaLabel;
            const id = element.id;
            if (id) {
                const label = document.querySelector('label[for="' + CSS.escape(id) + '"]');
                if (normalize(label?.textContent)) return normalize(label.textContent);
            }
            const wrappingLabel = element.closest('label');
            if (normalize(wrappingLabel?.textContent)) return normalize(wrappingLabel.textContent);
            if (!includeContent) return '';
            const alt = normalize(element.querySelector?.('img[alt]')?.getAttribute('alt'));
            const title = normalize(element.getAttribute('title'));
            const clone = element.cloneNode(true);
            clone.querySelectorAll?.('[aria-hidden="true"]').forEach((hidden) => hidden.remove());
            return normalize(clone.textContent) || alt || title;
        };
        const isVisible = (element) => {
            if (element.closest('[aria-hidden="true"], [inert]')) return false;
            const style = getComputedStyle(element);
            const rect = element.getBoundingClientRect();
            return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
        };
        const issue = (rule, element, detail) => ({
            rule,
            selector: selector(element),
            detail,
        });
        const issues = [];

        document.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach((element) => {
            if (isVisible(element) && !accessibleName(element, false)) {
                issues.push(issue('form-control-name', element, 'Controle de formulário sem nome acessível.'));
            }
        });
        document.querySelectorAll('button, a[href], [role="button"]').forEach((element) => {
            if (isVisible(element) && !accessibleName(element)) {
                issues.push(issue('interactive-name', element, 'Controle interativo visível sem nome acessível.'));
            }
        });
        document.querySelectorAll('img').forEach((element) => {
            if (!element.hasAttribute('alt')) {
                issues.push(issue('image-alt', element, 'Imagem sem atributo alt; use alt vazio se decorativa.'));
            }
        });
        document.querySelectorAll('[role="dialog"], dialog').forEach((element) => {
            if (!accessibleName(element)) {
                issues.push(issue('dialog-name', element, 'Diálogo sem aria-label ou aria-labelledby válido.'));
            }
        });
        document.querySelectorAll('table').forEach((element) => {
            if (!element.querySelector('caption') && !element.getAttribute('aria-label') && !element.getAttribute('aria-labelledby')) {
                issues.push(issue('table-name', element, 'Tabela sem caption ou nome acessível.'));
            }
            if (!element.querySelector('th')) {
                issues.push(issue('table-headers', element, 'Tabela sem cabeçalhos <th>.'));
            }
        });

        const headings = [...document.querySelectorAll('h1, h2, h3, h4, h5, h6')]
            .filter(isVisible)
            .map((element) => ({ level: Number(element.tagName.slice(1)), text: normalize(element.textContent), selector: selector(element) }));
        headings.forEach((heading, index) => {
            if (index > 0 && heading.level > headings[index - 1].level + 1) {
                issues.push({ rule: 'heading-order', selector: heading.selector, detail: 'Salto de h' + headings[index - 1].level + ' para h' + heading.level + '.' });
            }
        });
        const h1Count = headings.filter((heading) => heading.level === 1).length;
        if (h1Count !== 1) {
            issues.push({ rule: 'h1-count', selector: 'document', detail: 'Esperado 1 h1 visível; encontrados ' + h1Count + '.' });
        }

        const overflowElements = [...document.querySelectorAll('body *')]
            .filter(isVisible)
            .filter((element) => {
                const rect = element.getBoundingClientRect();
                return rect.left < -1 || rect.right > innerWidth + 1;
            })
            .slice(0, 20)
            .map((element) => ({ selector: selector(element), rect: element.getBoundingClientRect().toJSON() }));
        if (document.documentElement.scrollWidth > document.documentElement.clientWidth) {
            issues.push({ rule: 'horizontal-overflow', selector: 'document', detail: 'Documento excede a largura da viewport.' });
        }

        return {
            url: location.href,
            title: document.title,
            heading: document.querySelector('h1')?.innerText ?? null,
            text: document.body.innerText.slice(0, 1200),
            horizontalOverflow: document.documentElement.scrollWidth > document.documentElement.clientWidth,
            viewport: { width: innerWidth, height: innerHeight },
            document: {
                width: document.documentElement.scrollWidth,
                height: document.documentElement.scrollHeight
            },
            accessibility: {
                issues,
                issueCount: issues.length,
                countsByRule: issues.reduce((counts, current) => {
                    counts[current.rule] = (counts[current.rule] || 0) + 1;
                    return counts;
                }, {}),
                headings,
                overflowElements,
            },
        };
    })()`,
    returnByValue: true,
});

const screenshot = await call('Page.captureScreenshot', {
    format: 'png',
    captureBeyondViewport: true,
    fromSurface: true,
});

const absoluteOutput = resolve(outputPath);
mkdirSync(dirname(absoluteOutput), { recursive: true });
writeFileSync(absoluteOutput, Buffer.from(screenshot.data, 'base64'));

const reportPath = absoluteOutput.replace(/\.png$/i, '.json');
writeFileSync(reportPath, JSON.stringify(pageState.result.value, null, 2));

socket.close();

process.stdout.write(`${JSON.stringify({
    screenshot: absoluteOutput,
    report: reportPath,
    page: pageState.result.value,
}, null, 2)}\n`);
