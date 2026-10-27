<?php
declare(strict_types=1);

namespace HolyFace\Api;

use HolyFace\Api\Controllers\AiController;
use HolyFace\Api\Controllers\AuthController;
use HolyFace\Api\Controllers\GradeController;
use HolyFace\Api\Controllers\LegalController;
use HolyFace\Api\Controllers\PublicController;
use HolyFace\Api\Controllers\SettingsController;
use HolyFace\Api\Controllers\StudentController;
use HolyFace\Api\Controllers\TeacherController;
use HolyFace\Api\Http\Request;
use HolyFace\Api\Http\Response;
use HolyFace\Api\Security\AuthMiddleware;
use HolyFace\Api\Security\JwtService;
use HolyFace\Api\Services\AiStudyService;
use HolyFace\Api\Services\AuditService;
use HolyFace\Api\Services\ContentFilter;
use HolyFace\Api\Services\ConsentService;
use HolyFace\Api\Services\CsrfService;
use HolyFace\Api\Services\GradeComputationService;
use HolyFace\Api\Services\LoginRateLimiter;

final class Router
{
    public function dispatch(Request $request): void
    {
        $jwt = new JwtService();
        $authMw = new AuthMiddleware($jwt);
        $audit = new AuditService();
        $consent = new ConsentService();
        $auth = new AuthController($jwt, new LoginRateLimiter(), $audit, $consent);
        $legal = new LegalController($consent, $audit);
        $students = new StudentController($jwt, $consent);
        $teachers = new TeacherController();
        $grades = new GradeController(new GradeComputationService($audit), $audit);
        $settings = new SettingsController($audit);
        $public = new PublicController(new CsrfService());
        $ai = new AiController(new AiStudyService(new ContentFilter()), $students);

        $path = rtrim($request->path, '/') ?: '/';
        $method = $request->method;

        if ($path === '/health' && $method === 'GET') {
            $public->health();
            return;
        }
        if ($path === '/strands' && $method === 'GET') {
            $public->strands();
            return;
        }
        if ($path === '/announcements' && $method === 'GET') {
            $public->announcements($request);
            return;
        }
        if ($path === '/csrf' && $method === 'GET') {
            $public->csrfToken($request);
            return;
        }
        if ($path === '/study-materials' && $method === 'GET') {
            $public->studyMaterials($request);
            return;
        }
        if ($path === '/auth/login' && $method === 'POST') {
            $auth->login($request);
            return;
        }
        if ($path === '/contact' && $method === 'POST') {
            $public->contact($request);
            return;
        }
        if ($path === '/legal/policies' && $method === 'GET') {
            $legal->policies();
            return;
        }

        $ctx = $authMw->authenticate($request);
        if ($ctx === null) {
            return;
        }

        if ($path === '/auth/me' && $method === 'GET') {
            $auth->me($request, $ctx);
            return;
        }
        if ($path === '/auth/change-password' && $method === 'POST') {
            $auth->changePassword($request, $ctx);
            return;
        }
        if ($path === '/students/digital-id' && $method === 'GET') {
            $students->digitalId($request, $ctx);
            return;
        }
        if ($path === '/id/verify' && $method === 'POST') {
            $students->verifyId($request, $ctx);
            return;
        }
        if ($path === '/students/academics' && $method === 'GET') {
            $students->academics($request, $ctx);
            return;
        }
        if ($path === '/students/report-card' && $method === 'GET') {
            $students->reportCard($request, $ctx);
            return;
        }
        if ($path === '/students/my-grades' && $method === 'GET') {
            $students->academics($request, $ctx);
            return;
        }
        if ($path === '/students/schedule' && $method === 'GET') {
            $students->schedule($request, $ctx);
            return;
        }
        if ($path === '/students/announcements' && $method === 'GET') {
            $students->announcements($request, $ctx);
            return;
        }
        if ($path === '/students/subjects' && $method === 'GET') {
            $students->subjects($request, $ctx);
            return;
        }
        if ($path === '/students/profile' && $method === 'GET') {
            $students->profile($request, $ctx);
            return;
        }
        if ($path === '/students/consent' && $method === 'GET') {
            $students->consentStatus($request, $ctx);
            return;
        }
        if ($path === '/students/consent' && $method === 'POST') {
            $students->recordConsent($request, $ctx);
            return;
        }
        if ($path === '/legal/status' && $method === 'GET') {
            $legal->status($request, $ctx);
            return;
        }
        if ($path === '/legal/accept' && $method === 'POST') {
            $legal->accept($request, $ctx);
            return;
        }
        if ($path === '/admin/policies' && $method === 'PUT') {
            $legal->publish($request, $ctx);
            return;
        }
        if ($path === '/students/data-correction' && $method === 'POST') {
            $students->requestCorrection($request, $ctx);
            return;
        }
        if ($path === '/teachers/students' && $method === 'GET') {
            $teachers->listStudents($request, $ctx);
            return;
        }
        if ($path === '/teachers/sections' && $method === 'GET') {
            $teachers->mySections($request, $ctx);
            return;
        }
        if ($path === '/teachers/filters' && $method === 'GET') {
            $teachers->filters($request, $ctx);
            return;
        }
        if ($path === '/grades/components' && $method === 'GET') {
            $grades->listComponents($request, $ctx);
            return;
        }
        if ($path === '/grades/components' && $method === 'POST') {
            $grades->upsertComponent($request, $ctx);
            return;
        }
        if ($path === '/grades/grid' && $method === 'GET') {
            $grades->grid($request, $ctx);
            return;
        }
        if ($path === '/grades/items' && $method === 'POST') {
            $grades->createItem($request, $ctx);
            return;
        }
        if ($path === '/grades/items' && $method === 'PUT') {
            $grades->updateItem($request, $ctx);
            return;
        }
        if ($path === '/grades/batch' && $method === 'POST') {
            $grades->batchScores($request, $ctx);
            return;
        }
        if ($path === '/grades/submit-final' && $method === 'POST') {
            $grades->submitFinal($request, $ctx);
            return;
        }
        if ($path === '/grades/dashboard' && $method === 'GET') {
            $grades->dashboard($request, $ctx);
            return;
        }
        if ($path === '/grades/early-warning' && $method === 'GET') {
            $grades->earlyWarning($request, $ctx);
            return;
        }
        if ($path === '/grades/missing' && $method === 'GET') {
            $grades->missingGrades($request, $ctx);
            return;
        }
        if ($path === '/admin/settings' && $method === 'GET') {
            $settings->get($request, $ctx);
            return;
        }
        if ($path === '/admin/settings' && $method === 'PUT') {
            $settings->saveSettings($request, $ctx);
            return;
        }
        if ($path === '/admin/weight-profiles' && $method === 'PUT') {
            $settings->saveWeightProfiles($request, $ctx);
            return;
        }
        if ($path === '/admin/locks' && $method === 'POST') {
            $settings->setLock($request, $ctx);
            return;
        }
        if ($path === '/admin/transmutation' && $method === 'PUT') {
            $settings->saveTransmutation($request, $ctx);
            return;
        }
        if ($path === '/ai/study' && $method === 'POST') {
            $ai->generate($request, $ctx);
            return;
        }
        if ($path === '/ai/plan/tasks' && $method === 'GET') {
            $ai->planTasks($request, $ctx);
            return;
        }
        if ($path === '/ai/plan/tasks' && $method === 'POST') {
            $ai->toggleTask($request, $ctx);
            return;
        }
        if ($path === '/ai/flashcards/review' && $method === 'POST') {
            $ai->reviewCard($request, $ctx);
            return;
        }
        if ($path === '/ai/flashcards/decks' && $method === 'GET') {
            $ai->decks($request, $ctx);
            return;
        }
        if ($path === '/ai/flashcards/cards' && $method === 'GET') {
            $ai->deckCards($request, $ctx);
            return;
        }
        if ($path === '/ai/quiz/submit' && $method === 'POST') {
            $ai->submitQuiz($request, $ctx);
            return;
        }
        if ($path === '/ai/quiz/history' && $method === 'GET') {
            $ai->quizHistory($request, $ctx);
            return;
        }
        if ($path === '/ai/report' && $method === 'POST') {
            $ai->report($request, $ctx);
            return;
        }

        Response::error('Route not found.', 404);
    }
}
