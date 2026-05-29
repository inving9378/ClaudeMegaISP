module.exports = {
  apps: [
    {
      name: 'megaisp-referrals-worker',
      script: 'artisan',
      interpreter: 'php',
      args: 'queue:work database --queue=referrals,default --sleep=3 --tries=3 --max-time=3600 --memory=512',
      cwd: '/var/www/megaisp',
      instances: 2,
      exec_mode: 'fork',
      autorestart: true,
      watch: false,
      max_memory_restart: '512M',
      restart_delay: 5000,
      log_file: '/var/www/megaisp/storage/logs/referrals-worker.log',
      error_file: '/var/www/megaisp/storage/logs/referrals-worker-error.log',
      merge_logs: true,
      env: {
        APP_ENV: 'production',
        QUEUE_CONNECTION: 'database',
      },
    },
  ],
};
