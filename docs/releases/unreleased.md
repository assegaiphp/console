# Unreleased

## Application key generation

`assegai key:generate` initializes or rotates `APP_SECRET_KEY` independently of project creation. It creates a missing `.env` from `.env.example`, generates a 32-byte random key encoded as 64 hexadecimal characters, and preserves other dotenv settings. The generated key is never printed.

Existing non-placeholder keys require interactive confirmation or `--force`. Use `--directory` (`-d`) to select a workspace. Rotation may invalidate tokens or encrypted data that depend on the old key; restart long-running application processes after a change. Instances of the same environment must share a key.

`assegai new` uses the same generator and reports a failure if it cannot create the initial key. Generated-project and starter setup instructions now include the key command for cloned applications. Installation and update hooks do not rotate keys.
