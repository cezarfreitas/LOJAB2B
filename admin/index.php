<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administrativo - Catálogo de Chinelos</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 70px;
            width: 250px;
            height: calc(100vh - 70px);
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            overflow-y: auto;
        }

        .sidebar ul {
            list-style: none;
            padding: 1rem 0;
        }

        .sidebar li {
            margin: 0.5rem 0;
        }

        .sidebar a {
            display: block;
            padding: 0.75rem 1.5rem;
            color: #333;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .sidebar a:hover, .sidebar a.active {
            background-color: #f0f0f0;
            border-right: 3px solid #667eea;
        }

        .main-content {
            margin-left: 250px;
            padding: 2rem;
            min-height: calc(100vh - 70px);
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .dashboard-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .card-title {
            font-size: 1.1rem;
            color: #666;
            font-weight: 500;
        }

        .card-value {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
        }

        .card-change {
            font-size: 0.9rem;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
        }

        .positive {
            background-color: #d4edda;
            color: #155724;
        }

        .negative {
            background-color: #f8d7da;
            color: #721c24;
        }

        .chart-container {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .recent-orders {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .table-header {
            background: #f8f9fa;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 1rem 1.5rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #666;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-pendente {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-confirmado {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .status-processando {
            background-color: #d4edda;
            color: #155724;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
            transition: background-color 0.3s;
        }

        .btn-primary {
            background-color: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background-color: #5a6fd8;
        }

        .btn-success {
            background-color: #28a745;
            color: white;
        }

        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }

        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }

        .alert-warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }

        .icon {
            width: 24px;
            height: 24px;
            fill: currentColor;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">Catálogo de Chinelos - Admin</div>
        <div class="user-menu">
            <span>Bem-vindo, Administrador</span>
            <a href="logout.php" class="btn btn-primary">Sair</a>
        </div>
    </div>

    <div class="sidebar">
        <ul>
            <li><a href="#dashboard" class="active">📊 Dashboard</a></li>
            <li><a href="#produtos">👡 Produtos</a></li>
            <li><a href="#estoque">📦 Estoque</a></li>
            <li><a href="#pedidos">🛒 Pedidos</a></li>
            <li><a href="#clientes">👥 Clientes B2B</a></li>
            <li><a href="#categorias">📁 Categorias</a></li>
            <li><a href="#cores">🎨 Cores</a></li>
            <li><a href="#tamanhos">📏 Tamanhos</a></li>
            <li><a href="#relatorios">📈 Relatórios</a></li>
        </ul>
    </div>

    <div class="main-content">
        <!-- Dashboard Overview -->
        <div class="dashboard-grid">
            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-title">Total de Produtos</div>
                    <svg class="icon" viewBox="0 0 24 24">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                    </svg>
                </div>
                <div class="card-value">156</div>
                <div class="card-change positive">+12 este mês</div>
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-title">Pedidos Pendentes</div>
                    <svg class="icon" viewBox="0 0 24 24">
                        <path d="M7 4V2a1 1 0 0 1 2 0v2h6V2a1 1 0 0 1 2 0v2h1a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3h1z"/>
                    </svg>
                </div>
                <div class="card-value">23</div>
                <div class="card-change negative">+5 hoje</div>
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-title">Clientes Ativos</div>
                    <svg class="icon" viewBox="0 0 24 24">
                        <path d="M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0zM12 14a7 7 0 0 0-7 7h14a7 7 0 0 0-7-7z"/>
                    </svg>
                </div>
                <div class="card-value">89</div>
                <div class="card-change positive">+3 esta semana</div>
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-title">Vendas do Mês</div>
                    <svg class="icon" viewBox="0 0 24 24">
                        <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                </div>
                <div class="card-value">R$ 45.670</div>
                <div class="card-change positive">+18.2%</div>
            </div>
        </div>

        <!-- Alertas de Estoque Baixo -->
        <div class="alert alert-warning">
            <strong>⚠️ Atenção!</strong> Existem 8 produtos com estoque baixo que precisam de reposição.
            <a href="#estoque" class="btn btn-warning" style="margin-left: 1rem;">Ver Detalhes</a>
        </div>

        <!-- Gráfico de Vendas -->
        <div class="chart-container">
            <h3>Vendas dos Últimos 30 Dias</h3>
            <canvas id="salesChart" width="400" height="200"></canvas>
        </div>

        <!-- Pedidos Recentes -->
        <div class="recent-orders">
            <div class="table-header">
                <h3>Pedidos Recentes</h3>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Pedido</th>
                        <th>Cliente</th>
                        <th>Data</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>PED-20250713-0001</td>
                        <td>Loja ABC Calçados</td>
                        <td>13/07/2025</td>
                        <td>R$ 2.450,00</td>
                        <td><span class="status-badge status-pendente">Pendente</span></td>
                        <td>
                            <a href="#" class="btn btn-primary btn-sm">Ver</a>
                            <a href="#" class="btn btn-success btn-sm">Confirmar</a>
                        </td>
                    </tr>
                    <tr>
                        <td>PED-20250713-0002</td>
                        <td>Distribuidora XYZ</td>
                        <td>13/07/2025</td>
                        <td>R$ 5.280,00</td>
                        <td><span class="status-badge status-confirmado">Confirmado</span></td>
                        <td>
                            <a href="#" class="btn btn-primary btn-sm">Ver</a>
                        </td>
                    </tr>
                    <tr>
                        <td>PED-20250712-0045</td>
                        <td>Loja DEF Shoes</td>
                        <td>12/07/2025</td>
                        <td>R$ 1.890,00</td>
                        <td><span class="status-badge status-processando">Processando</span></td>
                        <td>
                            <a href="#" class="btn btn-primary btn-sm">Ver</a>
                        </td>
                    </tr>
                    <tr>
                        <td>PED-20250712-0044</td>
                        <td>Atacado GHI</td>
                        <td>12/07/2025</td>
                        <td>R$ 8.750,00</td>
                        <td><span class="status-badge status-confirmado">Confirmado</span></td>
                        <td>
                            <a href="#" class="btn btn-primary btn-sm">Ver</a>
                        </td>
                    </tr>
                    <tr>
                        <td>PED-20250711-0038</td>
                        <td>Varejo JKL</td>
                        <td>11/07/2025</td>
                        <td>R$ 3.120,00</td>
                        <td><span class="status-badge status-processando">Processando</span></td>
                        <td>
                            <a href="#" class="btn btn-primary btn-sm">Ver</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Produtos com Estoque Baixo -->
        <div class="recent-orders" style="margin-top: 2rem;">
            <div class="table-header">
                <h3>Produtos com Estoque Baixo</h3>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Variante</th>
                        <th>Estoque Atual</th>
                        <th>Estoque Mínimo</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Chinelo Básico Masculino</td>
                        <td>Preto - 42</td>
                        <td>3</td>
                        <td>10</td>
                        <td><span class="status-badge status-pendente">Crítico</span></td>
                        <td>
                            <a href="#" class="btn btn-warning btn-sm">Repor</a>
                        </td>
                    </tr>
                    <tr>
                        <td>Chinelo Conforto Feminino</td>
                        <td>Rosa - 37</td>
                        <td>6</td>
                        <td>15</td>
                        <td><span class="status-badge status-pendente">Baixo</span></td>
                        <td>
                            <a href="#" class="btn btn-warning btn-sm">Repor</a>
                        </td>
                    </tr>
                    <tr>
                        <td>Chinelo Esportivo</td>
                        <td>Azul - 40</td>
                        <td>8</td>
                        <td>12</td>
                        <td><span class="status-badge status-pendente">Baixo</span></td>
                        <td>
                            <a href="#" class="btn btn-warning btn-sm">Repor</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Simular dados para o gráfico (seria carregado via AJAX em produção)
        const ctx = document.getElementById('salesChart');
        if (ctx) {
            // Aqui você integraria com Chart.js ou similar
            // Para este exemplo, vamos apenas mostrar um placeholder
            ctx.style.background = '#f8f9fa';
            ctx.style.border = '2px dashed #dee2e6';
            ctx.style.display = 'flex';
            ctx.style.alignItems = 'center';
            ctx.style.justifyContent = 'center';
            ctx.innerHTML = '<p>Gráfico de Vendas - Integrar com Chart.js</p>';
        }

        // Navegação do sidebar (para SPA)
        document.querySelectorAll('.sidebar a').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Remove active class from all links
                document.querySelectorAll('.sidebar a').forEach(l => l.classList.remove('active'));
                
                // Add active class to clicked link
                this.classList.add('active');
                
                // Aqui você implementaria a lógica para carregar diferentes seções
                const section = this.getAttribute('href').substring(1);
                loadSection(section);
            });
        });

        function loadSection(section) {
            // Esta função carregaria o conteúdo específico de cada seção
            console.log('Carregando seção:', section);
            
            // Exemplo de como você poderia implementar:
            // fetch(`ajax/${section}.php`)
            //     .then(response => response.text())
            //     .then(html => {
            //         document.querySelector('.main-content').innerHTML = html;
            //     });
        }

        // Atualizar dados do dashboard periodicamente
        function updateDashboard() {
            // Aqui você faria requisições AJAX para atualizar os dados
            console.log('Atualizando dashboard...');
        }

        // Atualizar a cada 5 minutos
        setInterval(updateDashboard, 300000);

        // Menu mobile
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('open');
        }

        // Adicionar botão de menu para mobile
        if (window.innerWidth <= 768) {
            const header = document.querySelector('.header .logo');
            const menuBtn = document.createElement('button');
            menuBtn.innerHTML = '☰';
            menuBtn.style.background = 'none';
            menuBtn.style.border = 'none';
            menuBtn.style.color = 'white';
            menuBtn.style.fontSize = '1.5rem';
            menuBtn.onclick = toggleSidebar;
            header.parentNode.insertBefore(menuBtn, header);
        }
    </script>
</body>
</html>