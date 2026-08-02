#!/usr/bin/env bash

set -Eeuo pipefail

failures=0

pass() {
    printf '[OK] %s\n' "$1"
}

fail() {
    printf '[ERRO] %s\n' "$1" >&2
    failures=$((failures + 1))
}

check_file() {
    if [[ -f "$1" ]]; then
        pass "Arquivo presente: $1"
    else
        fail "Arquivo ausente: $1"
    fi
}

check_file .env
check_file artisan
check_file composer.lock
check_file public/build/manifest.json

if [[ -f .env ]]; then
    grep -Eq '^APP_ENV=production$' .env \
        && pass 'APP_ENV está em production' \
        || fail 'APP_ENV deve ser production'
    grep -Eq '^APP_DEBUG=(false|0)$' .env \
        && pass 'APP_DEBUG está desativado' \
        || fail 'APP_DEBUG deve estar desativado'
    grep -Eq '^APP_URL=https://' .env \
        && pass 'APP_URL utiliza HTTPS' \
        || fail 'APP_URL deve utilizar HTTPS'
    grep -Eq '^DB_CONNECTION=mysql$' .env \
        && pass 'A conexão configurada é MySQL' \
        || fail 'DB_CONNECTION deve ser mysql'

    if grep -Eq '=(CHANGE_ME|)$' .env; then
        fail 'O arquivo .env ainda contém valor vazio ou CHANGE_ME'
    else
        pass 'Nenhum placeholder óbvio foi encontrado no .env'
    fi
fi

for directory in storage bootstrap/cache; do
    if [[ -d "$directory" && -w "$directory" ]]; then
        pass "Diretório gravável: $directory"
    else
        fail "Diretório ausente ou sem escrita: $directory"
    fi
done

required_extensions=(ctype curl dom fileinfo gd intl mbstring openssl pdo_mysql tokenizer xml zip)
for extension in "${required_extensions[@]}"; do
    if php -r "exit(extension_loaded('$extension') ? 0 : 1);"; then
        pass "Extensão PHP disponível: $extension"
    else
        fail "Extensão PHP ausente: $extension"
    fi
done

if [[ -f vendor/autoload.php && -f .env ]]; then
    if php -r '
        require "vendor/autoload.php";
        $app = require "bootstrap/app.php";
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        Illuminate\Support\Facades\DB::connection()->getPdo();
    '; then
        pass 'Conexão com o banco estabelecida'
    else
        fail 'Não foi possível conectar ao banco'
    fi
else
    fail 'Dependências ou .env ausentes; conexão com o banco não foi testada'
fi

if (( failures > 0 )); then
    printf '\nPreflight reprovado com %d problema(s).\n' "$failures" >&2
    exit 1
fi

printf '\nPreflight aprovado. Nenhuma alteração foi feita.\n'
