import { expect, Page, test } from '@playwright/test';

async function login(page: Page, email: string) {
    const browserErrors: string[] = [];
    page.on('pageerror', error => browserErrors.push(error.message));
    page.on('console', message => {
        if (message.type() === 'error') browserErrors.push(message.text());
    });

    await page.goto('/login');
    await page.getByLabel('E-mail').fill(email);
    await page.getByLabel('Senha').fill('password');
    await page.getByRole('button', { name: 'Entrar' }).click();
    await expect(page).not.toHaveURL(/\/login$/);

    return browserErrors;
}

async function expectResponsive(page: Page) {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(2);
}

test('professor consulta créditos e gerencia aulas, atividades e revisões', async ({ page }) => {
    const errors = await login(page, 'teacher@email.com');
    await expect(page.getByText('Seus créditos deste mês')).toBeVisible();
    await expect(page.getByText('Limites individuais do professor')).toBeVisible();

    for (const [path, heading] of [['/lessons', 'Aulas'], ['/activities', 'Atividades'], ['/revisions', 'Revisões']] as const) {
        await page.goto(path);
        await expect(page.getByRole('heading', { name: heading, exact: true })).toBeVisible();
        await expectResponsive(page);
    }

    const mission = page.locator('article').filter({ hasText: 'Missão: dominar números e gráficos' });
    await mission.getByRole('link', { name: 'Editar' }).click();
    await expect(page.getByRole('heading', { name: 'Editar revisão' })).toBeVisible();
    await page.getByRole('link', { name: 'Gerar prompt para IA' }).click();
    await expect(page.getByText('Cole somente o JSON retornado')).toBeVisible();
    expect(errors).toEqual([]);
});

test('professor navega pelo wizard recuperável de oito etapas', async ({ page }) => {
    const errors = await login(page, 'teacher@email.com');
    await page.goto('/exams');
    await page.locator('.card')
        .filter({ hasText: 'Rascunho sem questões' })
        .getByRole('link', { name: 'Configurar' })
        .click();
    await expect(page.getByRole('heading', { name: 'Criação da Avaliação' })).toBeVisible();

    const wizard = page.getByRole('list', { name: 'Etapas da Avaliação' });
    await expect(wizard.getByRole('button')).toHaveCount(8);

    await page.route('**/exams/*/draft', async route => {
        const request = route.request().postDataJSON();
        expect(request.target_step).toBe('application');
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                saved: true,
                wizard: {
                    version: 1,
                    current_step: 'application',
                    completed_steps: [request.step],
                    revision: request.revision + 1,
                    updated_at: new Date().toISOString(),
                },
            }),
        });
    });

    await wizard.getByRole('button', { name: /Aplicação/ }).click();
    await expect(page.getByLabel('Modalidade')).toBeVisible();
    await expect(page.getByLabel('Apresentação digital')).toBeVisible();
    await expect(page.getByLabel('Questões por tela')).toBeVisible();
    await expect(page.getByText('Rascunho salvo')).toBeVisible();
    await expectResponsive(page);
    expect(errors).toEqual([]);
});

test('aluno acessa revisão interativa e vê gamificação privada', async ({ page }) => {
    const errors = await login(page, 'student@email.com');
    await page.goto('/student/revisions');
    await expect(page.getByRole('heading', { name: 'Revisões', exact: true })).toBeVisible();
    await expect(page.getByText('Missão: dominar números e gráficos')).toBeVisible();
    await expect(page.getByText('NÍVEL')).toBeVisible();
    await page.locator('article').filter({ hasText: 'Missão: dominar números e gráficos' }).getByRole('link').click();
    await expect(page.getByRole('button', { name: 'Iniciar ou continuar revisão' })).toBeVisible();
    await expectResponsive(page);
    expect(errors).toEqual([]);
});

test('superadministrador acessa consumo e cortesias sem dados de outro painel', async ({ page }) => {
    const errors = await login(page, 'admin@admin.com');
    await page.goto('/admin/usage');
    await expect(page.getByRole('heading', { name: /Consumo mensal/ })).toBeVisible();
    await page.goto('/admin/courtesies');
    await expect(page.getByRole('heading', { name: /Cortesias/ })).toBeVisible();
    await expect(page.getByText('Demonstração de créditos pedagógicos extras.')).toBeVisible();
    await expectResponsive(page);
    expect(errors).toEqual([]);
});
