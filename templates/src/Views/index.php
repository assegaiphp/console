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
</style>
<div class="container page-container">
  <div class="row">
    <img src="/images/logo.png" width="300" />
  </div>
  <br>
  <h2 class="title"><?= $title ?? 'N/A'; ?></h2>
  <br>
  <p>Congratulations! Your app, <?= $name ?? 'N/A'; ?> is running.</p>
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
    </div>
    <div class="spacer"></div>
  </div>
</div>