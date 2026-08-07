<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

// Autenticação
$routes->get('login', 'AuthController::loginForm');
$routes->post('login', 'AuthController::login');
// Auto-cadastro encerrado — contas são criadas em Configurações > Usuários.
$routes->get('registrar', 'AuthController::registerForm');
$routes->get('logout', 'AuthController::logout');

// Gestão de usuários e permissões
$routes->group('estudos/usuarios', [
    'namespace' => 'App\Controllers\Estudos',
    'filter'    => ['auth', 'perm:usuarios'],
], static function (RouteCollection $routes): void {
    $routes->get('/', 'UsuariosController::index');
    $routes->get('novo', 'UsuariosController::create');
    $routes->post('novo', 'UsuariosController::store');
    $routes->get('(:num)', 'UsuariosController::edit/$1');
    $routes->post('(:num)', 'UsuariosController::update/$1');
    $routes->post('(:num)/excluir', 'UsuariosController::delete/$1');
});

// Módulo de estudos (protegido)
$routes->group('estudos', ['namespace' => 'App\Controllers\Estudos', 'filter' => ['auth', 'perm:estudos']], static function (RouteCollection $routes): void {
    $routes->get('/', 'DashboardController::index');
    $routes->get('hoje', 'HojeController::index');
    $routes->get('cronograma', 'CronogramaController::index');
    $routes->get('kanban', 'KanbanController::index');
    $routes->get('revisoes', 'RevisoesController::index');
    $routes->get('questoes', 'QuestoesController::index');
    $routes->get('provas', 'ProvasController::index');
    $routes->get('desempenho', 'DesempenhoController::index');
    $routes->get('historico', 'HistoricoController::index');
    $routes->get('configuracoes', 'ConfiguracoesController::index');
    $routes->post('configuracoes', 'ConfiguracoesController::save');

    // API interna (JSON)
    $routes->group('api', static function (RouteCollection $routes): void {
        // Timer / sessões de estudo
        $routes->get('sessao/ativa', 'SessaoController::active');
        $routes->post('sessao/iniciar', 'SessaoController::start');
        $routes->post('sessao/(:num)/pausar', 'SessaoController::pause/$1');
        $routes->post('sessao/(:num)/retomar', 'SessaoController::resume/$1');
        $routes->post('sessao/(:num)/concluir', 'SessaoController::finish/$1');
        $routes->post('sessao/(:num)/cancelar', 'SessaoController::cancel/$1');

        // Tarefas
        $routes->post('tarefas/(:num)/concluir', 'TarefasController::complete/$1');
        $routes->post('tarefas/(:num)/reagendar', 'TarefasController::reschedule/$1');
        $routes->get('tarefas/(:num)', 'TarefasController::show/$1');
        $routes->post('tarefas/(:num)/observacao', 'TarefasController::addNote/$1');

        // Checklist
        $routes->post('checklist/(:num)/alternar', 'ChecklistController::toggle/$1');
        $routes->post('checklist', 'ChecklistController::create');
        $routes->post('checklist/(:num)/editar', 'ChecklistController::update/$1');
        $routes->post('checklist/(:num)/excluir', 'ChecklistController::delete/$1');
        $routes->post('checklist/reordenar', 'ChecklistController::reorder');

        // Kanban
        $routes->get('kanban/board', 'KanbanController::board');
        $routes->post('kanban/mover', 'KanbanController::move');

        // Revisões
        $routes->post('revisoes/(:num)/concluir', 'RevisoesController::complete/$1');
        $routes->post('revisoes/(:num)/reagendar', 'RevisoesController::reschedule/$1');
        $routes->post('revisoes/(:num)/ignorar', 'RevisoesController::skip/$1');

        // Registro de questões
        $routes->post('questoes', 'QuestoesController::store');
        $routes->post('questoes/(:num)/editar', 'QuestoesController::update/$1');
        $routes->post('questoes/(:num)/excluir', 'QuestoesController::delete/$1');

        // Provas antigas / materiais
        $routes->post('provas', 'ProvasController::store');
        $routes->post('provas/(:num)/editar', 'ProvasController::update/$1');
        $routes->post('provas/(:num)/desativar', 'ProvasController::deactivate/$1');
        $routes->post('provas/(:num)/excluir', 'ProvasController::delete/$1');
        $routes->post('provas/(:num)/tentativa', 'ProvasController::registerAttempt/$1');

        // Dados para gráficos
        $routes->get('desempenho/dados', 'DesempenhoController::data');
    });
});

// Módulo de flashcards (protegido)
$routes->group('flashcards', ['namespace' => 'App\Controllers\Flashcards', 'filter' => ['auth', 'perm:flashcards']], static function (RouteCollection $routes): void {
    $routes->get('/', 'DashboardController::index');

    // Sessão de revisão
    $routes->get('revisar', 'RevisaoController::index');
    $routes->get('historico', 'RevisaoController::history');

    // Cartões
    $routes->get('cartoes', 'CartoesController::index');

    // Geração por IA e fontes
    $routes->get('gerar', 'IaController::new', ['filter' => 'perm:flashcards_ia']);
    $routes->get('fontes', 'IaController::sources', ['filter' => 'perm:flashcards_ia']);

    // Estatísticas e configurações
    $routes->get('estatisticas', 'EstatisticasController::index');
    $routes->get('configuracoes', 'ConfiguracoesController::index');
    $routes->post('configuracoes', 'ConfiguracoesController::update');

    // Integrações externas
    $routes->get('integracoes', 'IntegracoesController::index', ['filter' => 'perm:flashcards_integracoes']);
    $routes->get('integracoes/documentacao', 'IntegracoesController::documentation', ['filter' => 'perm:flashcards_integracoes']);
    $routes->get('importacoes', 'IntegracoesController::pending', ['filter' => 'perm:flashcards_integracoes']);

    // API interna (JSON)
    $routes->group('api', static function (RouteCollection $routes): void {
        // Sessão de revisão
        $routes->post('sessao', 'RevisaoController::createSession');
        $routes->get('sessao/(:segment)/proximo', 'RevisaoController::next/$1');
        $routes->post('sessao/(:segment)/avaliar', 'RevisaoController::review/$1');
        $routes->post('sessao/(:segment)/desfazer', 'RevisaoController::undo/$1');
        $routes->post('sessao/(:segment)/encerrar', 'RevisaoController::finish/$1');

        // Cartões
        $routes->post('cartoes', 'CartoesController::create');
        $routes->get('cartoes/(:num)', 'CartoesController::show/$1');
        $routes->post('cartoes/(:num)/editar', 'CartoesController::update/$1');
        $routes->post('cartoes/(:num)/excluir', 'CartoesController::delete/$1');
        $routes->post('cartoes/(:num)/suspender', 'CartoesController::suspend/$1');
        $routes->post('cartoes/aprovar', 'CartoesController::approve');

        // Geração por IA
        $routes->group('', ['filter' => 'perm:flashcards_ia'], static function (RouteCollection $routes): void {
            $routes->post('gerar', 'IaController::createJob');
            $routes->post('gerar/(:segment)/processar', 'IaController::process/$1');
            $routes->get('gerar/(:segment)/status', 'IaController::status/$1');
            $routes->get('gerar/(:segment)/resultado', 'IaController::result/$1');
            $routes->post('gerar/(:segment)/aprovar', 'IaController::approve/$1');
            $routes->post('gerar/(:segment)/descartar', 'IaController::reject/$1');
            $routes->post('gerar/(:segment)/reprocessar', 'IaController::retry/$1');
            $routes->post('cartoes/(:num)/melhorar', 'IaController::improve/$1');
        });

        // Estatísticas
        $routes->get('estatisticas/dados', 'EstatisticasController::data');

        // Integrações
        $routes->post('tokens', 'IntegracoesController::createToken', ['filter' => 'perm:flashcards_integracoes']);
        $routes->post('tokens/(:segment)/revogar', 'IntegracoesController::revokeToken/$1', ['filter' => 'perm:flashcards_integracoes']);

        // Diagnóstico do serviço FSRS
        $routes->post('fsrs/testar', 'ConfiguracoesController::testFsrs');
    });
});

// API REST externa — autenticada por token Bearer (sem sessão, sem CSRF)
$routes->group('api/v1', ['namespace' => 'App\Controllers\Api'], static function (RouteCollection $routes): void {
    $routes->post('flashcards/import', 'FlashcardApiController::import', ['filter' => 'flashcardApi:import']);
    $routes->get('flashcards/imports/(:segment)', 'FlashcardApiController::status/$1', ['filter' => 'flashcardApi:read']);
    $routes->get('disciplines', 'FlashcardApiController::disciplines', ['filter' => 'flashcardApi:read']);
    $routes->get('categories', 'FlashcardApiController::categories', ['filter' => 'flashcardApi:read']);
    $routes->get('subjects', 'FlashcardApiController::subjects', ['filter' => 'flashcardApi:read']);
});
