<main class="starter-page">
  <div class="starter-glow starter-glow-left"></div>
  <div class="starter-glow starter-glow-right"></div>

  <section class="starter-hero">
    <div class="starter-copy">
      <div class="starter-brand">
        <img class="starter-logo" src="/images/logo.png" alt="AssegaiPHP logo">
      </div>

      <p class="starter-kicker"><?= htmlspecialchars($titleNote, ENT_QUOTES, 'UTF-8'); ?></p>
      <h1 class="starter-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
      <p class="starter-status"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></p>
      <p class="starter-summary"><?= htmlspecialchars($summary, ENT_QUOTES, 'UTF-8'); ?></p>

      <div class="starter-actions">
        <a class="starter-button starter-button-primary" href="<?= htmlspecialchars($guideLink, ENT_QUOTES, 'UTF-8'); ?>">Read the guide</a>
        <a class="starter-button" href="<?= htmlspecialchars($websiteLink, ENT_QUOTES, 'UTF-8'); ?>">Visit the website</a>
      </div>

      <div class="starter-link-grid">
        <a class="starter-link-card" href="<?= htmlspecialchars($websiteLink, ENT_QUOTES, 'UTF-8'); ?>">
          <span class="starter-link-label">Website</span>
          <span class="starter-link-copy">See the wider AssegaiPHP ecosystem and release updates.</span>
        </a>
        <a class="starter-link-card" href="<?= htmlspecialchars($guideLink, ENT_QUOTES, 'UTF-8'); ?>">
          <span class="starter-link-label">Get Started</span>
          <span class="starter-link-copy">Follow the setup and first-feature guides in your own environment.</span>
        </a>
        <a class="starter-link-card" href="<?= htmlspecialchars($supportLink, ENT_QUOTES, 'UTF-8'); ?>">
          <span class="starter-link-label">Support</span>
          <span class="starter-link-copy">Find community links, sponsorship info, and support channels.</span>
        </a>
      </div>
    </div>

    <div class="starter-panel">
      <div class="starter-terminal">
        <div class="starter-terminal-bar">
          <span></span>
          <span></span>
          <span></span>
        </div>
        <div class="starter-terminal-body">
          <div class="starter-terminal-line"><span class="starter-terminal-comment"># Welcome</span></div>
          <div class="starter-terminal-line"><span class="starter-terminal-key">$</span> <span class="starter-terminal-command">cd "<?= htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8'); ?>"</span></div>
          <div class="starter-terminal-line"><span class="starter-terminal-key">$</span> <span class="starter-terminal-command">assegai serve</span></div>
          <div class="starter-terminal-line"><span class="starter-terminal-key">$</span> <span class="starter-terminal-command">assegai g r posts</span></div>
          <div class="starter-terminal-line"><span class="starter-terminal-key">$</span> <span class="starter-terminal-command">assegai database:configure</span></div>
        </div>
      </div>

      <div class="starter-quickstart">
        <div class="starter-quickstart-header">
          <span class="starter-chip">Next steps</span>
          <p class="starter-quickstart-intro">A sensible path once your project is up and responding.</p>
        </div>
        <div class="starter-quickstart-grid">
          <div class="starter-quickstart-card">
            <span class="starter-quickstart-step">01</span>
            <strong>Read the guide</strong>
            <p>Use the getting-started guide to orient yourself around modules, routes, and the CLI flow.</p>
          </div>
          <div class="starter-quickstart-card">
            <span class="starter-quickstart-step">02</span>
            <strong>Generate a feature</strong>
            <p>Create controllers, services, DTOs, and entities in one pass with the CLI.</p>
          </div>
          <div class="starter-quickstart-card">
            <span class="starter-quickstart-step">03</span>
            <strong>Wire your data layer</strong>
            <p>Configure a database when you are ready to bring ORM-backed modules online.</p>
          </div>
        </div>
      </div>

      <div class="starter-socials" aria-label="Community links">
        <a href="https://github.com/assegaiphp" aria-label="GitHub" target="_blank" rel="noopener">
          <svg width="22" height="22" viewBox="0 0 25 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12.3047 0C5.50634 0 0 5.50942 0 12.3047C0 17.7423 3.52529 22.3535 8.41332 23.9787C9.02856 24.0946 9.25414 23.7142 9.25414 23.3871C9.25414 23.0949 9.24389 22.3207 9.23876 21.2953C5.81601 22.0377 5.09414 19.6444 5.09414 19.6444C4.53427 18.2243 3.72524 17.8449 3.72524 17.8449C2.61064 17.082 3.81137 17.0973 3.81137 17.0973C5.04697 17.1835 5.69604 18.3647 5.69604 18.3647C6.79321 20.2463 8.57636 19.7029 9.27978 19.3881C9.39052 18.5924 9.70736 18.0499 10.0591 17.7423C7.32641 17.4347 4.45429 16.3765 4.45429 11.6618C4.45429 10.3185 4.9311 9.22133 5.72065 8.36C5.58222 8.04931 5.16694 6.79833 5.82831 5.10337C5.82831 5.10337 6.85883 4.77319 9.2121 6.36459C10.1965 6.09082 11.2424 5.95546 12.2883 5.94931C13.3342 5.95546 14.3801 6.09082 15.3644 6.36459C17.7023 4.77319 18.7328 5.10337 18.7328 5.10337C19.3942 6.79833 18.9789 8.04931 18.8559 8.36C19.6403 9.22133 20.1171 10.3185 20.1171 11.6618C20.1171 16.3888 17.2409 17.4296 14.5031 17.7321C14.9338 18.1012 15.3337 18.8559 15.3337 20.0084C15.3337 21.6552 15.3183 22.978 15.3183 23.3779C15.3183 23.7009 15.5336 24.0854 16.1642 23.9623C21.0871 22.3484 24.6094 17.7341 24.6094 12.3047C24.6094 5.50942 19.0999 0 12.3047 0Z"></path>
          </svg>
        </a>
        <a href="https://x.com/assegaiphp" aria-label="X" target="_blank" rel="noopener">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"></path>
          </svg>
        </a>
        <a href="https://youtube.com/@atatusoft-ltd" aria-label="YouTube" target="_blank" rel="noopener">
          <svg width="26" height="18" viewBox="0 0 29 20" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M27.4896 1.52422C27.9301 1.96749 28.2463 2.51866 28.4068 3.12258C29.0004 5.35161 29.0004 10 29.0004 10C29.0004 10 29.0004 14.6484 28.4068 16.8774C28.2463 17.4813 27.9301 18.0325 27.4896 18.4758C27.0492 18.9191 26.5 19.2389 25.8972 19.4032C23.6778 20 14.8068 20 14.8068 20C14.8068 20 5.93586 20 3.71651 19.4032C3.11363 19.2389 2.56449 18.9191 2.12405 18.4758C1.68361 18.0325 1.36732 17.4813 1.20683 16.8774C0.613281 14.6484 0.613281 10 0.613281 10C0.613281 10 0.613281 5.35161 1.20683 3.12258C1.36732 2.51866 1.68361 1.96749 2.12405 1.52422C2.56449 1.08095 3.11363 0.76113 3.71651 0.596774C5.93586 0 14.8068 0 14.8068 0C14.8068 0 23.6778 0 25.8972 0.596774C26.5 0.76113 27.0492 1.08095 27.4896 1.52422ZM19.3229 10L11.9036 5.77905V14.221L19.3229 10Z"></path>
          </svg>
        </a>
      </div>
    </div>
  </section>
</main>
