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
    await expect(page.getByRole('heading', { name: 'Versão histórica protegida' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Gerar prompt para IA' })).toHaveCount(0);
    expect(errors).toEqual([]);
});

test('professor acessa o gerenciador de materiais reutilizáveis', async ({ page }) => {
    const errors = await login(page, 'teacher@email.com');
    await page.goto('/question-resources');
    await expect(page.getByRole('heading', { name: 'Materiais de apoio', exact: true })).toBeVisible();
    const resourceScopes = page.getByRole('navigation', { name: 'Escopos dos materiais de apoio' });
    await expect(resourceScopes.getByRole('link')).toHaveCount(4);
    await expect(resourceScopes.locator('a[href*="scope=institution"]')).toBeVisible();
    await expect(resourceScopes.locator('a[href*="scope=platform"]')).toBeVisible();
    await expectResponsive(page);

    await page.goto('/questions?tab=platform');
    await expect(page.getByRole('heading', { name: 'Banco de Questões', exact: true })).toBeVisible();
    const questionScopes = page.getByLabel('Visualizações do banco de questões');
    await expect(questionScopes.getByRole('link')).toHaveCount(4);
    await expect(questionScopes.getByRole('link', { name: /Públic/ })).toHaveAttribute('aria-current', 'page');
    await expectResponsive(page);

    await page.goto('/question-resources');
    await page.getByRole('link', { name: 'Novo recurso' }).click();
    await expect(page.getByRole('heading', { name: 'Novo material de apoio' })).toBeVisible();
    await expect(page.getByLabel('Título')).toBeVisible();
    await expect(page.getByLabel('Tipo')).toBeVisible();
    await expect(page.getByLabel('Visibilidade')).toBeVisible();
    await expect(page.getByLabel(/Arquivo privado/)).toBeVisible();
    await expectResponsive(page);

    await page.goto('/public-catalog/submissions');
    await expect(page.getByRole('heading', { name: 'Minhas contribuições públicas' })).toBeVisible();
    await expect(page.getByText('Reputação', { exact: true })).toBeVisible();
    await expectResponsive(page);

    await page.goto('/questions?tab=mine');
    const firstQuestion = page.locator('.card').first();
    await firstQuestion.getByRole('button').first().click();
    const submitLink = firstQuestion.getByRole('link', { name: 'Enviar ao catálogo' });
    if (await submitLink.isVisible()) {
        await submitLink.click();
        await expect(page.getByRole('heading', { name: 'Enviar para moderação' })).toBeVisible();
        await expect(page.getByLabel(/Confirmo que tenho os direitos necessários/)).toBeVisible();
        await expect(page.getByLabel(/Aceito os termos de contribuição/)).toBeVisible();
        await expectResponsive(page);
    }
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

    await page.unroute('**/exams/*/draft');
    await page.route('**/exams/*/draft', async route => {
        const request = route.request().postDataJSON();
        expect(request.target_step).toBe('appearance');
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                saved: true,
                wizard: {
                    version: 1,
                    current_step: 'appearance',
                    completed_steps: ['information'],
                    revision: request.revision + 1,
                    updated_at: new Date().toISOString(),
                },
            }),
        });
    });
    await wizard.getByRole('button', { name: /Aparência/ }).click();
    const layoutCatalog = page.getByRole('radiogroup', { name: 'Layout da Avaliação' });
    await expect(layoutCatalog.getByRole('radio')).toHaveCount(10);
    await page.getByText('Cabeçalho da Avaliação', { exact: true }).click();
    const headerCatalog = page.getByRole('radiogroup', { name: 'Cabeçalho da Avaliação' });
    await expect(headerCatalog.getByRole('radio')).toHaveCount(10);
    await expect(page.getByText(/Embaralhamento e individualização/)).toBeVisible();
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

test('aluno acompanha avaliação e resultado sem overflow', async ({ page }) => {
    const errors = await login(page, 'student@email.com');
    await expect(page.getByRole('heading', { name: 'Portal do Aluno' })).toBeVisible();

    const assessment = page.locator('article').filter({ hasText: /Diagnóstico de Matemática/ });
    await expect(assessment).toBeVisible();
    await expect(assessment.getByText('Professor', { exact: true })).toBeVisible();
    await expect(assessment.getByText('Instituição', { exact: true })).toBeVisible();
    await expect(assessment.getByText('Disciplina', { exact: true })).toBeVisible();
    await expect(assessment.getByText('Tentativas utilizadas', { exact: true })).toBeVisible();
    await expectResponsive(page);

    await assessment.getByRole('link', { name: 'Ver resultados' }).click();
    await expect(page.getByRole('heading', { name: 'Resumo da avaliação' })).toBeVisible();
    await expect(page.getByText('Tentativa corrigida')).toBeVisible();
    await expectResponsive(page);
    expect(errors).toEqual([]);
});

test('aluno recebe aulas e atividades com progresso responsivo', async ({ page }) => {
    const errors = await login(page, 'student@email.com');
    await page.goto('/student/pedagogical');
    await expect(page.getByRole('heading', { name: 'Aulas e atividades', exact: true })).toBeVisible();
    const lesson = page.locator('article').filter({ hasText: 'Sistema decimal' });
    await expect(lesson).toBeVisible();
    await lesson.getByRole('link').click();
    await expect(page.getByRole('heading', { name: /Sistema decimal/ })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Marcar aula como concluída' })).toBeVisible();
    await expectResponsive(page);

    await page.goto('/student/pedagogical');
    const activity = page.locator('article').filter({ hasText: 'Desafio colaborativo' });
    await expect(activity).toBeVisible();
    await activity.getByRole('link').click();
    await expect(page.getByRole('button', { name: 'Iniciar tentativa' })).toBeVisible();
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
    await page.goto('/admin/public-catalog');
    await expect(page.getByRole('heading', { name: 'Moderação do catálogo público' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Submissões' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Denúncias abertas' })).toBeVisible();
    await expectResponsive(page);
    expect(errors).toEqual([]);
});

test('professor personaliza cabecalho versionado no editor visual', async ({ page }) => {
    const errors = await login(page, 'teacher@email.com');
    await page.goto('/appearance-templates');
    await expect(page.getByRole('heading', { name: /Layouts e cabe/ })).toBeVisible();
    expect(await page.locator('[data-template-kind="assessment_layout"]').count()).toBeGreaterThanOrEqual(10);
    expect(await page.locator('[data-template-kind="assessment_header"]').count()).toBeGreaterThanOrEqual(10);
    await expectResponsive(page);

    const systemHeader = page.locator('[data-template-kind="assessment_header"]').first();
    await systemHeader.getByRole('button', { name: /Personalizar/ }).click();
    await expect(page.getByRole('heading', { name: /C.pia de/ })).toBeVisible();
    await expect(page.getByLabel(/Canvas visual/)).toBeVisible();
    await page.getByRole('button', { name: 'Campo', exact: true }).click();
    await expect(page.locator('option[value="student.name"]')).toHaveCount(1);
    await page.getByLabel(/Resumo da vers/).fill('Campo adicionado pelo teste visual');
    await page.getByRole('button', { name: /Salvar nova vers/ }).click();
    await expect(page.getByText(/Vers.o atual 2/)).toBeVisible();
    await expectResponsive(page);
    expect(errors).toEqual([]);
});
