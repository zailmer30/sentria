<?php

use App\Http\Controllers\Admin\BrandingController;
use App\Http\Controllers\Admin\ChamberChannelController;
use App\Http\Controllers\Admin\MonitoringController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\AgendaItemController;
use App\Http\Controllers\AiCompareController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\CommitteeController;
use App\Http\Controllers\CommitteeMemberController;
use App\Http\Controllers\CommitteeReferralController;
use App\Http\Controllers\CommitteeReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentAnnotationController;
use App\Http\Controllers\DocumentConsistencyController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentGrantController;
use App\Http\Controllers\DocumentReferralController;
use App\Http\Controllers\DocumentRelatedController;
use App\Http\Controllers\DocumentSummaryController;
use App\Http\Controllers\DocumentTransitionController;
use App\Http\Controllers\DocumentVersionController;
use App\Http\Controllers\FloorRecognitionController;
use App\Http\Controllers\HallDisplayController;
use App\Http\Controllers\LegislationImportController;
use App\Http\Controllers\LegislativeHistoryController;
use App\Http\Controllers\LegislativeSignedCopyController;
use App\Http\Controllers\MinutesConsiderationController;
use App\Http\Controllers\MinutesController;
use App\Http\Controllers\MotionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrdinanceController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PrivateNoteController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicationController;
use App\Http\Controllers\ResolutionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SessionAssistantController;
use App\Http\Controllers\SessionCaptureController;
use App\Http\Controllers\SessionChatController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SessionFloorCacheController;
use App\Http\Controllers\SessionFloorController;
use App\Http\Controllers\SessionFloorReferralController;
use App\Http\Controllers\SessionGuestController;
use App\Http\Controllers\TranscriptController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VotingController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/portal');

Route::prefix('portal')->name('portal.')->group(function (): void {
    Route::get('/', [PortalController::class, 'home'])->name('home');
    Route::get('/search', [PortalController::class, 'search'])
        ->middleware('throttle:portal-search')
        ->name('search');
    Route::get('/documents/{publicSlug}', [PortalController::class, 'document'])->name('documents.show');
    Route::get('/documents/{publicSlug}/signed-copy/preview', [PortalController::class, 'documentSignedCopyPreview'])
        ->name('documents.signed-copy.preview');
    Route::get('/documents/{publicSlug}/signed-copy/download', [PortalController::class, 'documentSignedCopyDownload'])
        ->name('documents.signed-copy.download');
    Route::get('/ordinances/{identifier}/signed-copy/preview', [PortalController::class, 'ordinanceSignedCopyPreview'])
        ->name('ordinances.signed-copy.preview');
    Route::get('/ordinances/{identifier}/signed-copy/download', [PortalController::class, 'ordinanceSignedCopyDownload'])
        ->name('ordinances.signed-copy.download');
    Route::get('/ordinances/{identifier}', [PortalController::class, 'ordinance'])->name('ordinances.show');
    Route::get('/resolutions/{identifier}/signed-copy/preview', [PortalController::class, 'resolutionSignedCopyPreview'])
        ->name('resolutions.signed-copy.preview');
    Route::get('/resolutions/{identifier}/signed-copy/download', [PortalController::class, 'resolutionSignedCopyDownload'])
        ->name('resolutions.signed-copy.download');
    Route::get('/resolutions/{identifier}', [PortalController::class, 'resolution'])->name('resolutions.show');
    Route::get('/sessions', [PortalController::class, 'sessions'])->name('sessions.index');
    Route::get('/minutes/{minute}', [PortalController::class, 'minutes'])->name('minutes.show');
    Route::get('/history/{slug}', [PortalController::class, 'history'])->name('history.show');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
});

Route::middleware(['auth'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');
    Route::get('/admin/monitoring', MonitoringController::class)->name('admin.monitoring');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::get('/settings/branding', [BrandingController::class, 'edit'])->name('settings.branding.edit');
    Route::put('/settings/branding', [BrandingController::class, 'update'])->name('settings.branding.update');
    Route::post('/settings/branding/reset', [BrandingController::class, 'reset'])->name('settings.branding.reset');
    Route::get('/settings/chamber-channels', [ChamberChannelController::class, 'edit'])->name('settings.chamber-channels.edit');
    Route::get('/settings/chamber-channels/levels', [ChamberChannelController::class, 'levels'])->name('settings.chamber-channels.levels');
    Route::post('/settings/chamber-channels/starter', [ChamberChannelController::class, 'downloadStarter'])
        ->middleware('throttle:6,1')
        ->name('settings.chamber-channels.starter');
    Route::put('/settings/chamber-channels', [ChamberChannelController::class, 'update'])->name('settings.chamber-channels.update');
    Route::put('/settings/chamber-channels/feed', [ChamberChannelController::class, 'updateDefaultFeed'])
        ->name('settings.chamber-channels.feed');
    Route::put('/settings/chamber-channels/device', [ChamberChannelController::class, 'updateDevice'])
        ->name('settings.chamber-channels.device');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
    Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::patch('/notifications/{notification}', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('documents/create', [DocumentController::class, 'create'])->name('documents.create');
    Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('documents/{document:slug}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('documents/{document:slug}/edit', [DocumentController::class, 'edit'])->name('documents.edit');
    Route::put('documents/{document:slug}', [DocumentController::class, 'update'])->name('documents.update');
    Route::delete('documents/{document:slug}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    Route::post('documents/{document:slug}/archive', [DocumentController::class, 'archive'])->name('documents.archive');
    Route::post('documents/{document:slug}/seal', [DocumentController::class, 'seal'])->name('documents.seal');
    Route::post('documents/{slug}/restore', [DocumentController::class, 'restore'])->name('documents.restore');

    Route::post('documents/{document:slug}/versions', [DocumentVersionController::class, 'store'])
        ->name('documents.versions.store');
    Route::post('documents/{document:slug}/versions/{version}/retry-processing', [DocumentVersionController::class, 'retryProcessing'])
        ->name('documents.versions.retry-processing');
    Route::get('documents/{document:slug}/versions/{version}/download', [DocumentVersionController::class, 'download'])
        ->name('documents.versions.download');
    Route::get('documents/{document:slug}/versions/{version}/preview', [DocumentVersionController::class, 'preview'])
        ->name('documents.versions.preview');
    Route::get('documents/{document:slug}/versions/{version}/view', [DocumentVersionController::class, 'viewer'])
        ->name('documents.versions.view');
    Route::get('documents/{document:slug}/versions/{version}/annotations', [DocumentAnnotationController::class, 'show'])
        ->middleware('throttle:60,1')
        ->name('documents.versions.annotations.show');
    Route::put('documents/{document:slug}/versions/{version}/annotations', [DocumentAnnotationController::class, 'update'])
        ->middleware('throttle:60,1')
        ->name('documents.versions.annotations.update');
    Route::get('documents/{document:slug}/versions/compare', [DocumentVersionController::class, 'compare'])
        ->name('documents.versions.compare');

    Route::post('documents/{document:slug}/grants', [DocumentGrantController::class, 'store'])
        ->name('documents.grants.store');
    Route::delete('documents/{document:slug}/grants/{grant}', [DocumentGrantController::class, 'destroy'])
        ->name('documents.grants.destroy');

    Route::post('documents/{document:slug}/transition', DocumentTransitionController::class)
        ->name('documents.transition');
    Route::put('documents/{document:slug}/referral', [DocumentReferralController::class, 'update'])
        ->name('documents.referral.update');

    Route::get('committees', [CommitteeController::class, 'index'])->name('committees.index');
    Route::get('committees/create', [CommitteeController::class, 'create'])->name('committees.create');
    Route::post('committees', [CommitteeController::class, 'store'])->name('committees.store');
    Route::get('committees/{committee:slug}', [CommitteeController::class, 'show'])->name('committees.show');
    Route::post('committees/{committee:slug}/members', [CommitteeMemberController::class, 'store'])
        ->name('committees.members.store');

    Route::post('referrals', [CommitteeReferralController::class, 'store'])->name('referrals.store');
    Route::put('referrals/{referral}', [CommitteeReferralController::class, 'update'])->name('referrals.update');

    Route::post('reports', [CommitteeReportController::class, 'store'])->name('reports.store');
    Route::post('reports/{report}/submit-for-review', [CommitteeReportController::class, 'submitForReview'])
        ->name('reports.submit-for-review');
    Route::post('reports/{report}/return-to-draft', [CommitteeReportController::class, 'returnToDraft'])
        ->name('reports.return-to-draft');
    Route::post('reports/{report}/submit', [CommitteeReportController::class, 'submit'])->name('reports.submit');
    Route::post('reports/{report}/adopt', [CommitteeReportController::class, 'adopt'])->name('reports.adopt');

    Route::get('ordinances/import', [LegislationImportController::class, 'createOrdinance'])->name('ordinances.import.create');
    Route::get('ordinances/import/template', [LegislationImportController::class, 'templateOrdinance'])->name('ordinances.import.template');
    Route::post('ordinances/import', [LegislationImportController::class, 'storeOrdinance'])->name('ordinances.import.store');
    Route::get('ordinances/import/{batch}', [LegislationImportController::class, 'showOrdinance'])->name('ordinances.import.show');
    Route::post('ordinances/import/{batch}', [LegislationImportController::class, 'commitOrdinance'])->name('ordinances.import.commit');
    Route::resource('ordinances', OrdinanceController::class);
    Route::post('ordinances/{ordinance}/signed-copy', [LegislativeSignedCopyController::class, 'storeOrdinance'])
        ->name('ordinances.signed-copy.store');
    Route::delete('ordinances/{ordinance}/signed-copy', [LegislativeSignedCopyController::class, 'destroyOrdinance'])
        ->name('ordinances.signed-copy.destroy');
    Route::get('ordinances/{ordinance}/signed-copy/preview', [LegislativeSignedCopyController::class, 'previewOrdinance'])
        ->name('ordinances.signed-copy.preview');
    Route::get('ordinances/{ordinance}/signed-copy/download', [LegislativeSignedCopyController::class, 'downloadOrdinance'])
        ->name('ordinances.signed-copy.download');

    Route::get('resolutions/import', [LegislationImportController::class, 'createResolution'])->name('resolutions.import.create');
    Route::get('resolutions/import/template', [LegislationImportController::class, 'templateResolution'])->name('resolutions.import.template');
    Route::post('resolutions/import', [LegislationImportController::class, 'storeResolution'])->name('resolutions.import.store');
    Route::get('resolutions/import/{batch}', [LegislationImportController::class, 'showResolution'])->name('resolutions.import.show');
    Route::post('resolutions/import/{batch}', [LegislationImportController::class, 'commitResolution'])->name('resolutions.import.commit');
    Route::resource('resolutions', ResolutionController::class);
    Route::post('resolutions/{resolution}/signed-copy', [LegislativeSignedCopyController::class, 'storeResolution'])
        ->name('resolutions.signed-copy.store');
    Route::delete('resolutions/{resolution}/signed-copy', [LegislativeSignedCopyController::class, 'destroyResolution'])
        ->name('resolutions.signed-copy.destroy');
    Route::get('resolutions/{resolution}/signed-copy/preview', [LegislativeSignedCopyController::class, 'previewResolution'])
        ->name('resolutions.signed-copy.preview');
    Route::get('resolutions/{resolution}/signed-copy/download', [LegislativeSignedCopyController::class, 'downloadResolution'])
        ->name('resolutions.signed-copy.download');

    Route::get('publications', [PublicationController::class, 'index'])->name('publications.index');
    Route::get('publications/create', [PublicationController::class, 'create'])->name('publications.create');
    Route::post('publications', [PublicationController::class, 'store'])->name('publications.store');
    Route::get('publications/{publication:public_slug}', [PublicationController::class, 'show'])->name('publications.show');
    Route::post('publications/{publication:public_slug}/transition', [PublicationController::class, 'transition'])->name('publications.transition');
    Route::post('documents/{document:slug}/publication', [PublicationController::class, 'createFromDocument'])->name('documents.publication.create');

    Route::redirect('/legislation', '/ordinances')->name('legislation.index');

    Route::resource('sessions', SessionController::class)->except(['destroy']);
    Route::post('sessions/{session}/schedule', [SessionController::class, 'schedule'])->name('sessions.schedule');
    Route::post('sessions/{session}/prepare-agenda', [SessionController::class, 'prepareAgenda'])->name('sessions.prepare-agenda');
    Route::post('sessions/{session}/start', [SessionController::class, 'start'])->name('sessions.start');
    Route::post('sessions/{session}/suspend', [SessionController::class, 'suspend'])->name('sessions.suspend');
    Route::post('sessions/{session}/recess', [SessionController::class, 'recess'])->name('sessions.recess');
    Route::post('sessions/{session}/resume', [SessionController::class, 'resume'])->name('sessions.resume');
    Route::post('sessions/{session}/adjourn', [SessionController::class, 'adjourn'])->name('sessions.adjourn');
    Route::post('sessions/{session}/recording', [SessionController::class, 'updateRecording'])->name('sessions.recording.update');
    Route::post('sessions/{session}/voting-mode', [SessionController::class, 'updateVotingMode'])->name('sessions.voting-mode.update');

    Route::post('sessions/{session}/agenda', [AgendaItemController::class, 'store'])->name('sessions.agenda.store');
    Route::put('sessions/{session}/agenda/{agendaItem}', [AgendaItemController::class, 'update'])->name('sessions.agenda.update');
    Route::post('sessions/{session}/agenda/{agendaItem}/documents', [AgendaItemController::class, 'bindDocuments'])->name('sessions.agenda.documents.store');
    Route::post('sessions/{session}/agenda/{agendaItem}/minutes', [MinutesConsiderationController::class, 'upload'])->name('sessions.agenda.minutes.store');
    Route::post('sessions/{session}/agenda/{agendaItem}/minutes-corrections', [MinutesConsiderationController::class, 'storeCorrection'])->name('sessions.agenda.minutes-corrections.store');
    Route::put('sessions/{session}/agenda/{agendaItem}/minutes-corrections/{correction}', [MinutesConsiderationController::class, 'updateCorrection'])->name('sessions.agenda.minutes-corrections.update');
    Route::delete('sessions/{session}/agenda/{agendaItem}/minutes-corrections/{correction}', [MinutesConsiderationController::class, 'destroyCorrection'])->name('sessions.agenda.minutes-corrections.destroy');
    Route::patch('sessions/{session}/agenda/{agendaItem}/minutes-corrections/{correction}/apply', [MinutesConsiderationController::class, 'applyCorrection'])->name('sessions.agenda.minutes-corrections.apply');
    Route::delete('sessions/{session}/agenda/{agendaItem}', [AgendaItemController::class, 'destroy'])->name('sessions.agenda.destroy');
    Route::post('sessions/{session}/agenda/reorder', [AgendaItemController::class, 'reorder'])->name('sessions.agenda.reorder');
    Route::post('sessions/{session}/agenda/advance', [AgendaItemController::class, 'advance'])->name('sessions.agenda.advance');
    Route::post('sessions/{session}/agenda/retreat', [AgendaItemController::class, 'retreat'])->name('sessions.agenda.retreat');
    Route::post('sessions/{session}/agenda/{agendaItem}/committee-hour-motion', [AgendaItemController::class, 'recordCommitteeHourMotion'])->name('sessions.agenda.committee-hour-motion');
    Route::post('sessions/{session}/agenda/begin-heading-votes', [AgendaItemController::class, 'beginHeadingVotes'])->name('sessions.agenda.begin-heading-votes');
    Route::post('sessions/{session}/agenda/{agendaItem}/second-reading', [AgendaItemController::class, 'calendarSecondReading'])->name('sessions.agenda.second-reading');
    Route::post('sessions/{session}/agenda/{agendaItem}/third-reading', [AgendaItemController::class, 'calendarThirdReading'])->name('sessions.agenda.third-reading');
    Route::post('sessions/{session}/agenda/{agendaItem}/postpone', [AgendaItemController::class, 'postpone'])->name('sessions.agenda.postpone');
    Route::post('sessions/{session}/agenda/{agendaItem}/undo-postpone', [AgendaItemController::class, 'undoPostpone'])->name('sessions.agenda.undo-postpone');
    Route::post('sessions/{session}/calendar/second-reading', [AgendaItemController::class, 'calendarSecondReadingDocument'])->name('sessions.calendar.second-reading');
    Route::post('sessions/{session}/calendar/third-reading', [AgendaItemController::class, 'calendarThirdReadingDocument'])->name('sessions.calendar.third-reading');
    Route::post('sessions/{session}/calendar/postpone', [AgendaItemController::class, 'postponeDocument'])->name('sessions.calendar.postpone');
    Route::post('sessions/{session}/calendar/bulk', [AgendaItemController::class, 'bulkCalendarRoute'])->name('sessions.calendar.bulk');

    Route::get('sessions/{session}/attendance', [AttendanceController::class, 'index'])->name('sessions.attendance.index');
    Route::put('sessions/{session}/attendance', [AttendanceController::class, 'update'])->name('sessions.attendance.update');
    Route::post('sessions/{session}/guests', [SessionGuestController::class, 'store'])->name('sessions.guests.store');
    Route::put('sessions/{session}/guests/{sessionGuest}', [SessionGuestController::class, 'update'])->name('sessions.guests.update');
    Route::delete('sessions/{session}/guests/{sessionGuest}', [SessionGuestController::class, 'destroy'])->name('sessions.guests.destroy');
    Route::get('sessions/{session}/documents', [SessionController::class, 'documents'])->name('sessions.documents.index');

    Route::get('sessions/{session}/floor/cache', SessionFloorCacheController::class)->name('sessions.floor.cache');
    Route::get('sessions/{session}/floor/member', [SessionFloorController::class, 'boardMember'])->name('sessions.floor.member');
    Route::get('sessions/{session}/floor/secretariat', [SessionFloorController::class, 'secretariat'])->name('sessions.floor.secretariat');
    Route::get('sessions/{session}/floor/minutes', [SessionFloorController::class, 'minutes'])->name('sessions.floor.minutes');
    Route::get('sessions/{session}/floor/recording', [SessionFloorController::class, 'recording'])->name('sessions.floor.recording');
    Route::put('sessions/{session}/floor/minutes', [SessionFloorController::class, 'updateMinutes'])->name('sessions.floor.minutes.update');
    Route::get('sessions/{session}/floor/presiding', [SessionFloorController::class, 'presidingOfficer'])->name('sessions.floor.presiding');
    Route::get('sessions/{session}/floor/dashboard', [SessionFloorController::class, 'dashboard'])->name('sessions.floor.dashboard');
    Route::post('sessions/{session}/floor/refer', SessionFloorReferralController::class)->name('sessions.floor.refer');

    Route::prefix('sessions/{session}/chat')->name('sessions.chat.')->group(function (): void {
        Route::get('/', [SessionChatController::class, 'index'])->name('index');
        Route::get('/directory', [SessionChatController::class, 'directory'])->name('directory');
        Route::post('/direct', [SessionChatController::class, 'storeDirect'])->name('direct');
        Route::post('/groups', [SessionChatController::class, 'storeGroup'])->name('groups');
        Route::get('/{conversation}', [SessionChatController::class, 'show'])->name('show');
        Route::post('/{conversation}/messages', [SessionChatController::class, 'storeMessage'])
            ->middleware('throttle:60,1')
            ->name('messages');
        Route::post('/{conversation}/read', [SessionChatController::class, 'markRead'])->name('read');
        Route::post('/{conversation}/participants', [SessionChatController::class, 'addParticipants'])->name('participants.store');
        Route::delete('/{conversation}/participants/{user}', [SessionChatController::class, 'removeParticipant'])->name('participants.destroy');
    });

    Route::get('sessions/{session}/assistant', [SessionAssistantController::class, 'show'])->name('sessions.assistant.show');
    Route::post('sessions/{session}/assistant/search', [SessionAssistantController::class, 'search'])->name('sessions.assistant.search');
    Route::post('sessions/{session}/assistant/ask', [SessionAssistantController::class, 'ask'])
        ->middleware('throttle:ai-ask')
        ->name('sessions.assistant.ask');

    Route::get('sessions/{session}/capture', [SessionCaptureController::class, 'show'])->name('sessions.capture.show');
    Route::middleware('throttle:chamber-capture')->group(function (): void {
        Route::get('sessions/{session}/capture/state', [SessionCaptureController::class, 'state'])->name('sessions.capture.state');
        Route::post('sessions/{session}/capture/heartbeat', [SessionCaptureController::class, 'heartbeat'])->name('sessions.capture.heartbeat');
        Route::post('sessions/{session}/capture/chunks', [SessionCaptureController::class, 'chunks'])->name('sessions.capture.chunks');
    });

    Route::get('sessions/{session}/transcript', [TranscriptController::class, 'show'])->name('sessions.transcript.show');
    Route::post('sessions/{session}/transcript', [TranscriptController::class, 'store'])->name('sessions.transcript.store');
    Route::get('sessions/{session}/transcript/search', [TranscriptController::class, 'search'])->name('sessions.transcript.search');
    Route::patch('sessions/{session}/transcript/{transcript}/segments/{segmentIndex}', [TranscriptController::class, 'correct'])
        ->name('sessions.transcript.correct');
    Route::get('sessions/{session}/transcript/{transcript}/segments/{segmentIndex}/edits', [TranscriptController::class, 'segmentEdits'])
        ->name('sessions.transcript.segment-edits');

    Route::post('sessions/{session}/motions', [MotionController::class, 'store'])->name('sessions.motions.store');
    Route::post('sessions/{session}/motions/{motion}/second', [MotionController::class, 'second'])->name('sessions.motions.second');
    Route::post('sessions/{session}/motions/{motion}/withdraw', [MotionController::class, 'withdraw'])->name('sessions.motions.withdraw');
    Route::post('sessions/{session}/motions/{motion}/rule', [MotionController::class, 'rule'])->name('sessions.motions.rule');

    Route::post('sessions/{session}/recognition', [FloorRecognitionController::class, 'store'])->name('sessions.recognition.store');
    Route::post('sessions/{session}/recognition/{recognition}/cancel', [FloorRecognitionController::class, 'cancel'])->name('sessions.recognition.cancel');
    Route::post('sessions/{session}/recognition/{recognition}/recognize', [FloorRecognitionController::class, 'recognize'])->name('sessions.recognition.recognize');
    Route::post('sessions/{session}/recognition/{recognition}/dismiss', [FloorRecognitionController::class, 'dismiss'])->name('sessions.recognition.dismiss');

    Route::post('sessions/{session}/voting/open', [VotingController::class, 'open'])->name('sessions.voting.open');
    Route::post('sessions/{session}/voting/cast', [VotingController::class, 'cast'])->name('sessions.voting.cast');
    Route::post('sessions/{session}/voting/close', [VotingController::class, 'close'])->name('sessions.voting.close');
    Route::get('sessions/{session}/voting/results', [VotingController::class, 'results'])->name('sessions.voting.results');

    Route::post('sessions/{session}/hall/document', [HallDisplayController::class, 'showDocument'])->name('sessions.hall.document');
    Route::post('sessions/{session}/hall/report', [HallDisplayController::class, 'showReport'])->name('sessions.hall.report');
    Route::post('sessions/{session}/hall/item', [HallDisplayController::class, 'showItem'])->name('sessions.hall.item');
    Route::post('sessions/{session}/hall/results', [HallDisplayController::class, 'showResults'])->name('sessions.hall.results');
    Route::post('sessions/{session}/hall/view', [HallDisplayController::class, 'updateView'])->name('sessions.hall.view');

    Route::resource('minutes', MinutesController::class)->except(['destroy']);
    Route::get('minutes/{minute}/pdf', [MinutesController::class, 'pdf'])->name('minutes.pdf');
    Route::post('minutes/{minute}/generate-draft', [MinutesController::class, 'generateDraft'])->name('minutes.generate-draft');
    Route::post('minutes/{minute}/accept-draft', [MinutesController::class, 'acceptDraft'])->name('minutes.accept-draft');
    Route::post('minutes/{minute}/review', [MinutesController::class, 'review'])->name('minutes.review');
    Route::post('minutes/{minute}/approve', [MinutesController::class, 'approve'])->name('minutes.approve');
    Route::post('minutes/{minute}/finalize', [MinutesController::class, 'finalize'])->name('minutes.finalize');
    Route::post('minutes/{minute}/archive', [MinutesController::class, 'archive'])->name('minutes.archive');
    Route::post('minutes/{minute}/confirm-suggestion', [MinutesController::class, 'confirmSuggestion'])->name('minutes.confirm-suggestion');

    Route::get('ordinances/{ordinance}/history', [LegislativeHistoryController::class, 'forOrdinance'])
        ->name('ordinances.history');
    Route::get('resolutions/{resolution}/history', [LegislativeHistoryController::class, 'forResolution'])
        ->name('resolutions.history');
    Route::get('documents/{document:slug}/history', [LegislativeHistoryController::class, 'forDocument'])
        ->name('documents.history');

    Route::post('documents/{document:slug}/summary', [DocumentSummaryController::class, 'store'])
        ->name('documents.summary');

    Route::match(['get', 'post'], 'documents/{document:slug}/related', [DocumentRelatedController::class, 'index'])
        ->name('documents.related');

    Route::get('documents/{document:slug}/consistency', [DocumentConsistencyController::class, 'show'])
        ->name('documents.consistency.show');
    Route::post('documents/{document:slug}/consistency', [DocumentConsistencyController::class, 'store'])
        ->name('documents.consistency');

    Route::get('ai', [AiController::class, 'index'])->name('ai.index');
    Route::get('ai/compare', [AiCompareController::class, 'index'])->name('ai.compare.index');
    Route::get('ai/compare/result', [AiCompareController::class, 'compare'])->name('ai.compare');
    Route::post('ai/ask', [AiController::class, 'ask'])
        ->middleware('throttle:ai-ask')
        ->name('ai.ask');
    Route::get('ai/conversations/{conversation}', [AiController::class, 'show'])->name('ai.conversations.show');

    Route::post('private-notes', [PrivateNoteController::class, 'store'])->name('private-notes.store');
    Route::put('private-notes/{privateNote}', [PrivateNoteController::class, 'update'])->name('private-notes.update');
    Route::delete('private-notes/{privateNote}', [PrivateNoteController::class, 'destroy'])->name('private-notes.destroy');

    Route::post('bookmarks', [BookmarkController::class, 'store'])->name('bookmarks.store');
    Route::delete('bookmarks/{bookmark}', [BookmarkController::class, 'destroy'])->name('bookmarks.destroy');
});
