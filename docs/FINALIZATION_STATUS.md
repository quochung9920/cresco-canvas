# Cresco Canvas finalization status

This branch exists to execute the automated Release Hardening workflow against the current finalization candidate.

The stable `1.0.0` version must not be declared certified until all automated gates pass and the repository contains objective manual evidence required by `scripts/check-stable-release.mjs`, including accessibility manual verification and the exact artifact SHA-256.

Commercial readiness implemented by this candidate includes provider-backed license activation/deactivation, authenticated update manifests with stable/beta channels, copy-safe Site Health diagnostics, onboarding completion state, and previous-version metadata for rollback support.
