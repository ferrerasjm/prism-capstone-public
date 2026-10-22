# Classifier deployment and defense check

Deploy the PHP, PowerShell, and Python changes together. The startup/watchdog script
now reads Laravel's effective configuration and passes the same token to Python
through its environment. It probes authenticated `/predict`, attempts one local
restart on failure, and waits up to approximately three minutes for readiness.
Failure raises an error instead of printing a successful service check.
Disabled ML configuration is explicitly reported as a warning.

After deploying, run in VPS PowerShell from the application directory:

```powershell
php artisan config:cache
& .\deploy\windows\start-prism-services.ps1 -AppDir 'C:\laragon\www\prism'
php artisan tinker --execute="dump(collect(app(\App\Services\AdvisoryOffenseClassificationService::class)->classify('During lunch break, he punched another student in the face following a verbal disagreement.'))->only(['source', 'status', 'guardrail'])->all());"
```

Require `hybrid_model`, `success`, and guardrail reason `explicit_punch_to_person`.
Refresh the open case and verify the MAJOR-11 handbook-rule suggestion and injury
verification notice. A model score is not a calibrated probability of guilt.

If the model was already healthy when code was deployed outside the deployment
scripts, restart that checkout's Python classifier so it loads the updated rules.
The auto-deploy script restarts it on code changes; unchanged checkouts no longer
stop runtime services. After changing `.env`, rebuild configuration and run the
startup script again. Do not print or share the runtime configuration helper's
output: it contains the service token.

Before defense, run the above check and review `storage/logs/ml-service.err.log`
and Laravel fallback logs (now including HTTP status). No service can guarantee
zero downtime; retain the explicit fallback and human confirmation workflow.
