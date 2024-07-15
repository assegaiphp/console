<style>
  html {
    background-color: var(--primary);
    color: var(--primary-text);
  }

  .page-container .row {
    margin-bottom: 1rem;
  }

  .page-container .row a {
    color: var(--primary-text);
    text-decoration: none;
    padding: 1rem 0;
    border-radius: .4rem;
    background-color: rgba(255, 255, 255, .1);
    transition: background-color .3s ease-in-out 0s;
    display: flex;
    justify-content: center;
    align-items: center;
  }

  .page-container .row a:hover {
    background-color: rgba(255, 255, 255, .2);
  }

  .page-container .row.social-links a,
  .page-container .row.social-links a:hover {
    background-color: transparent;
  }

  .page-container .row.social-links a svg {
    fill: var(--primary-light);
    transition: fill .25s linear 0s;
  }

  .page-container .row.social-links a:hover svg {
    fill: var(--primary-text);
  }
</style>
<div class="container page-container">
  <div class="row">
    <img src="/images/logo.png" width="300" />
  </div>
  <h2 class="title"><?= $title ?? 'N/A'; ?></h2>
  <h3 class="subtitle"><?= $subtitle ?? 'N/A'; ?></h3>
  <br>
  <div class="row">
    <div class="spacer"></div>
    <div class="col col-4">
      <div class="row">
        <a class="col col-6" href="<?= $welcomeLink ?? ''; ?>">Welcome</a>
        <a class="col col-6" href="<?= $documentationLink ?? ''; ?>">Documentation</a>
      </div>
      <div class="row">
        <a class="col col-6" href="<?= $getStartedLink ?? ''; ?>">Get Started</a>
        <a class="col col-6" href="<?= $donateLink ?? ''; ?>">Donate</a>
      </div>
      <div class="social-links row row-center">
        <a class="col" href="https://github.com/assegaiphp" aria-label="Github" target="_blank" rel="noopener">
          <svg width="25" height="24" viewBox="0 0 25 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12.3047 0C5.50634 0 0 5.50942 0 12.3047C0 17.7423 3.52529 22.3535 8.41332 23.9787C9.02856 24.0946 9.25414 23.7142 9.25414 23.3871C9.25414 23.0949 9.24389 22.3207 9.23876 21.2953C5.81601 22.0377 5.09414 19.6444 5.09414 19.6444C4.53427 18.2243 3.72524 17.8449 3.72524 17.8449C2.61064 17.082 3.81137 17.0973 3.81137 17.0973C5.04697 17.1835 5.69604 18.3647 5.69604 18.3647C6.79321 20.2463 8.57636 19.7029 9.27978 19.3881C9.39052 18.5924 9.70736 18.0499 10.0591 17.7423C7.32641 17.4347 4.45429 16.3765 4.45429 11.6618C4.45429 10.3185 4.9311 9.22133 5.72065 8.36C5.58222 8.04931 5.16694 6.79833 5.82831 5.10337C5.82831 5.10337 6.85883 4.77319 9.2121 6.36459C10.1965 6.09082 11.2424 5.95546 12.2883 5.94931C13.3342 5.95546 14.3801 6.09082 15.3644 6.36459C17.7023 4.77319 18.7328 5.10337 18.7328 5.10337C19.3942 6.79833 18.9789 8.04931 18.8559 8.36C19.6403 9.22133 20.1171 10.3185 20.1171 11.6618C20.1171 16.3888 17.2409 17.4296 14.5031 17.7321C14.9338 18.1012 15.3337 18.8559 15.3337 20.0084C15.3337 21.6552 15.3183 22.978 15.3183 23.3779C15.3183 23.7009 15.5336 24.0854 16.1642 23.9623C21.0871 22.3484 24.6094 17.7341 24.6094 12.3047C24.6094 5.50942 19.0999 0 12.3047 0Z"></path>
          </svg>
        </a>
        <a class="col" href="https://x.com/assegaiphp" aria-label="X" target="_blank" rel="noopener">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"></path>
          </svg>
        </a>
        <a class="col" href="https://www.youtube.com/@atatusoft7573" aria-label="Youtube" target="_blank" rel="noopener">
          <svg width="29" height="20" viewBox="0 0 29 20" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M27.4896 1.52422C27.9301 1.96749 28.2463 2.51866 28.4068 3.12258C29.0004 5.35161 29.0004 10 29.0004 10C29.0004 10 29.0004 14.6484 28.4068 16.8774C28.2463 17.4813 27.9301 18.0325 27.4896 18.4758C27.0492 18.9191 26.5 19.2389 25.8972 19.4032C23.6778 20 14.8068 20 14.8068 20C14.8068 20 5.93586 20 3.71651 19.4032C3.11363 19.2389 2.56449 18.9191 2.12405 18.4758C1.68361 18.0325 1.36732 17.4813 1.20683 16.8774C0.613281 14.6484 0.613281 10 0.613281 10C0.613281 10 0.613281 5.35161 1.20683 3.12258C1.36732 2.51866 1.68361 1.96749 2.12405 1.52422C2.56449 1.08095 3.11363 0.76113 3.71651 0.596774C5.93586 0 14.8068 0 14.8068 0C14.8068 0 23.6778 0 25.8972 0.596774C26.5 0.76113 27.0492 1.08095 27.4896 1.52422ZM19.3229 10L11.9036 5.77905V14.221L19.3229 10Z"></path>
          </svg>
        </a>
      </div>
    </div>
    <div class="spacer"></div>
  </div>
</div>