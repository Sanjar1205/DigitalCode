<?php
$pageTitle = 'Tizim sozlamalari';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'CSRF token xato'); redirect($_SERVER['REQUEST_URI']);
    }
    
    foreach ($_POST as $key => $value) {
        if (in_array($key, ['csrf_token', 'action'])) continue;
        setSetting($key, $value);
    }
    setFlash('success', 'Sozlamalar saqlandi');
    redirect($_SERVER['REQUEST_URI']);
}

$settings = [
    'site_name' => getSetting('site_name', 'CodeAcademy'),
    'primary_color' => getSetting('primary_color', '#4F46E5'),
    'secondary_color' => getSetting('secondary_color', '#10B981'),
    'site_language' => getSetting('site_language', 'uz'),
    'judge0_api_key' => getSetting('judge0_api_key', ''),
    'judge0_api_url' => getSetting('judge0_api_url', 'https://judge0-ce.p.rapidapi.com'),
    'jdoodle_client_id' => getSetting('jdoodle_client_id', ''),
    'jdoodle_client_secret' => getSetting('jdoodle_client_secret', ''),
    'gemini_api_key' => getSetting('gemini_api_key', ''),
    'openai_api_key' => getSetting('openai_api_key', ''),
    'claude_api_key' => getSetting('claude_api_key', ''),
    'ai_model' => getSetting('ai_model', 'claude-3-5-sonnet-20241022'),
    'max_login_attempts' => getSetting('max_login_attempts', '5'),
    'session_timeout' => getSetting('session_timeout', '3600'),
    'enable_ai_assistant' => getSetting('enable_ai_assistant', '1'),
    'default_passing_score' => getSetting('default_passing_score', '60'),
];

require_once __DIR__ . '/../includes/header.php';
?>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= e(Auth::getCsrfToken()) ?>">
    
    <!-- Tablar -->
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item">
            <button type="button" class="nav-link active" data-bs-toggle="tab" data-bs-target="#general">
                <i class="fas fa-cog"></i> Umumiy
            </button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#api">
                <i class="fas fa-key"></i> API kalitlar
            </button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#security">
                <i class="fas fa-shield-alt"></i> Xavfsizlik
            </button>
        </li>
    </ul>
    
    <div class="tab-content">
        <!-- Umumiy -->
        <div class="tab-pane fade show active" id="general">
            <div class="card">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Sayt nomi</label>
                            <input type="text" name="site_name" class="form-control" value="<?= e($settings['site_name']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Sayt tili</label>
                            <select name="site_language" class="form-select">
                                <option value="uz" <?= $settings['site_language'] === 'uz' ? 'selected' : '' ?>>O'zbek</option>
                                <option value="ru" <?= $settings['site_language'] === 'ru' ? 'selected' : '' ?>>Русский</option>
                                <option value="en" <?= $settings['site_language'] === 'en' ? 'selected' : '' ?>>English</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Asosiy rang</label>
                            <input type="color" name="primary_color" class="form-control" value="<?= e($settings['primary_color']) ?>" style="height: 45px;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ikkilamchi rang</label>
                            <input type="color" name="secondary_color" class="form-control" value="<?= e($settings['secondary_color']) ?>" style="height: 45px;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Test o'tish bali (% — default)</label>
                            <input type="number" name="default_passing_score" class="form-control" value="<?= e($settings['default_passing_score']) ?>" min="0" max="100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">AI Yordamchi</label>
                            <select name="enable_ai_assistant" class="form-select">
                                <option value="1" <?= $settings['enable_ai_assistant'] === '1' ? 'selected' : '' ?>>Yoqilgan</option>
                                <option value="0" <?= $settings['enable_ai_assistant'] === '0' ? 'selected' : '' ?>>O'chirilgan</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- API -->
       <div class="tab-pane fade" id="api">
            <div class="card mb-3">
                <div class="card-header">
                    <h3><i class="fas fa-code"></i> JDoodle (Kod kompilyatori)</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <span>JDoodle API kalitini <a href="https://www.jdoodle.com/compiler-api" target="_blank">jdoodle.com</a> saytidan bepul olishingiz mumkin. Kuniga 200 ta so'rov bepul.</span>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">JDoodle Client ID</label>
                            <input type="password" name="jdoodle_client_id" class="form-control" value="<?= e($settings['jdoodle_client_id']) ?>" placeholder="Client ID...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">JDoodle Client Secret</label>
                            <input type="password" name="jdoodle_client_secret" class="form-control" value="<?= e($settings['jdoodle_client_secret']) ?>" placeholder="Client Secret...">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-robot"></i> AI Yordamchi</h3>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">AI Provider</label>
                            <select name="ai_model" class="form-select">
                                <option value="gemini-flash-latest" <?= $settings['ai_model'] === 'gemini-flash-latest' ? 'selected' : '' ?>>Gemini Flash (Latest)</option>
                                <option value="gemini-1.5-pro-latest" <?= $settings['ai_model'] === 'gemini-1.5-pro-latest' ? 'selected' : '' ?>>Gemini Pro (Latest)</option>
                                <option value="claude-3-5-sonnet-20241022" <?= $settings['ai_model'] === 'claude-3-5-sonnet-20241022' ? 'selected' : '' ?>>Claude 3.5 Sonnet</option>
                                <option value="claude-3-haiku-20240307" <?= $settings['ai_model'] === 'claude-3-haiku-20240307' ? 'selected' : '' ?>>Claude 3 Haiku</option>
                                <option value="gpt-4o" <?= $settings['ai_model'] === 'gpt-4o' ? 'selected' : '' ?>>GPT-4o</option>
                                <option value="gpt-4o-mini" <?= $settings['ai_model'] === 'gpt-4o-mini' ? 'selected' : '' ?>>GPT-4o mini</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Gemini API Key</label>
                            <input type="password" name="gemini_api_key" class="form-control" value="<?= e($settings['gemini_api_key'] ?? '') ?>" placeholder="AIzaSy...">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Claude API Key</label>
                            <input type="password" name="claude_api_key" class="form-control" value="<?= e($settings['claude_api_key']) ?>" placeholder="sk-ant-api03-...">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">OpenAI API Key</label>
                            <input type="password" name="openai_api_key" class="form-control" value="<?= e($settings['openai_api_key']) ?>" placeholder="sk-...">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Xavfsizlik -->
        <div class="tab-pane fade" id="security">
            <div class="card">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Maksimal login urinish</label>
                            <input type="number" name="max_login_attempts" class="form-control" value="<?= e($settings['max_login_attempts']) ?>" min="3" max="10">
                            <small class="text-muted">Bundan keyin 15 daqiqa bloklanadi</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Session timeout (sekund)</label>
                            <input type="number" name="session_timeout" class="form-control" value="<?= e($settings['session_timeout']) ?>" min="600">
                            <small class="text-muted">3600 = 1 soat</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="mt-3" style="display: flex; gap: 0.75rem;">
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="fas fa-save"></i> Sozlamalarni saqlash
        </button>
        <a href="<?= SITE_URL ?>/admin/dashboard.php" class="btn btn-secondary">Bekor qilish</a>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
