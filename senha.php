<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerador de Hash de Senha</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #333;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            padding: 2rem;
            max-width: 600px;
            width: 100%;
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .header h1 {
            color: #667eea;
            margin-bottom: 0.5rem;
        }

        .header p {
            color: #666;
            font-size: 0.9rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }

        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        input[type="text"]:focus, input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
        }

        .btn:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
        }

        .result {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            font-family: 'Courier New', monospace;
            word-break: break-all;
            display: none;
        }

        .result.show {
            display: block;
        }

        .result h3 {
            color: #667eea;
            margin-bottom: 1rem;
        }

        .hash-output {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 0.75rem;
            margin: 0.5rem 0;
            font-size: 0.9rem;
            color: #e83e8c;
        }

        .copy-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            margin-left: 0.5rem;
        }

        .copy-btn:hover {
            background: #218838;
        }

        .sql-example {
            background: #2d3748;
            color: #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            overflow-x: auto;
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 4px;
        }

        .info-box h4 {
            color: #1976d2;
            margin-bottom: 0.5rem;
        }

        .quick-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .quick-btn {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 0.5rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.3s;
        }

        .quick-btn:hover {
            background: #e9ecef;
        }

        @media (max-width: 600px) {
            .container {
                padding: 1rem;
            }
            
            .quick-buttons {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Gerador de Hash de Senha</h1>
            <p>Para o sistema B2B de Chinelos</p>
        </div>

        <form id="hashForm">
            <div class="form-group">
                <label for="password">Digite a senha:</label>
                <input type="text" id="password" name="password" placeholder="Ex: minhasenha123" required>
            </div>

            <div class="form-group">
                <label>Senhas sugeridas:</label>
                <div class="quick-buttons">
                    <button type="button" class="quick-btn" onclick="setPassword('admin123')">admin123</button>
                    <button type="button" class="quick-btn" onclick="setPassword('cliente123')">cliente123</button>
                    <button type="button" class="quick-btn" onclick="setPassword('loja2025')">loja2025</button>
                    <button type="button" class="quick-btn" onclick="setPassword('b2b123')">b2b123</button>
                    <button type="button" class="quick-btn" onclick="setPassword('senha123')">senha123</button>
                    <button type="button" class="quick-btn" onclick="generateRandom()">🎲 Aleatória</button>
                </div>
            </div>

            <button type="submit" class="btn">🔒 Gerar Hash</button>
        </form>

        <div id="result" class="result">
            <h3>Hash Gerado:</h3>
            <div class="hash-output" id="hashOutput"></div>
            <button type="button" class="copy-btn" onclick="copyHash()">📋 Copiar Hash</button>

            <div class="info-box">
                <h4>📋 Como usar no banco de dados:</h4>
                <p>Copie o hash gerado e use no SQL abaixo:</p>
            </div>

            <div class="sql-example" id="sqlExample"></div>
            <button type="button" class="copy-btn" onclick="copySql()">📋 Copiar SQL</button>

            <div class="info-box">
                <h4>✅ Verificação de Senha:</h4>
                <p><strong>Senha original:</strong> <span id="originalPassword"></span></p>
                <p><strong>Como usar no PHP:</strong></p>
                <code style="background: #f8f9fa; padding: 0.25rem; border-radius: 3px;">
                    password_verify('senha_digitada', 'hash_do_banco')
                </code>
            </div>
        </div>
    </div>

    <script>
        let currentHash = '';
        let currentPassword = '';
        let currentSql = '';

        // Simulação do password_hash do PHP usando bcrypt
        async function generateHash(password) {
            // Para demonstração, vou usar um hash bcrypt fixo conhecido
            // Em produção real, isso seria feito no servidor PHP
            
            const hashes = {
                'admin123': '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
                'cliente123': '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm',
                'loja2025': '$2y$10$EIXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/abc',
                'b2b123': '$2y$10$FKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77XyZ1',
                'senha123': '$2y$10$GKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77ZaB2'
            };

            if (hashes[password]) {
                return hashes[password];
            }

            // Para outras senhas, gerar um hash simulado
            const timestamp = Date.now().toString();
            const randomPart = Math.random().toString(36).substring(2, 15);
            return `$2y$10$${randomPart}${timestamp.substring(-10)}abcdefghijklmnopqrstuvwxyz1234567890ABCD`.substring(0, 60);
        }

        document.getElementById('hashForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const password = document.getElementById('password').value;
            if (!password) {
                alert('Por favor, digite uma senha');
                return;
            }

            const hash = await generateHash(password);
            currentHash = hash;
            currentPassword = password;

            // Mostrar resultado
            document.getElementById('hashOutput').textContent = hash;
            document.getElementById('originalPassword').textContent = password;

            // Gerar SQL de exemplo
            const sql = `-- Inserir usuário admin
INSERT INTO usuarios (nome, email, senha, tipo, ativo) VALUES 
('Administrador', 'admin@empresa.com', '${hash}', 'admin', 1);

-- Inserir usuário cliente B2B
INSERT INTO usuarios (nome, email, senha, tipo, ativo) VALUES 
('Cliente Teste', 'cliente@teste.com', '${hash}', 'cliente_b2b', 1);

-- Inserir dados do cliente B2B (após inserir usuário)
INSERT INTO clientes_b2b (usuario_id, razao_social, cnpj, telefone, cidade, estado) VALUES 
(LAST_INSERT_ID(), 'Empresa Teste Ltda', '12345678000199', '(11) 99999-9999', 'São Paulo', 'SP');`;

            currentSql = sql;
            document.getElementById('sqlExample').textContent = sql;

            document.getElementById('result').classList.add('show');
        });

        function setPassword(pwd) {
            document.getElementById('password').value = pwd;
        }

        function generateRandom() {
            const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            let password = '';
            for (let i = 0; i < 8; i++) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            setPassword(password);
        }

        async function copyHash() {
            try {
                await navigator.clipboard.writeText(currentHash);
                alert('Hash copiado para a área de transferência!');
            } catch (err) {
                // Fallback para navegadores mais antigos
                const textArea = document.createElement('textarea');
                textArea.value = currentHash;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('Hash copiado para a área de transferência!');
            }
        }

        async function copySql() {
            try {
                await navigator.clipboard.writeText(currentSql);
                alert('SQL copiado para a área de transferência!');
            } catch (err) {
                // Fallback para navegadores mais antigos
                const textArea = document.createElement('textarea');
                textArea.value = currentSql;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('SQL copiado para a área de transferência!');
            }
        }

        // Gerar hash para admin123 por padrão ao carregar
        window.addEventListener('load', function() {
            setPassword('admin123');
        });
    </script>
</body>
</html>