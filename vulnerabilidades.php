<?php
// Objetivo: localizar, explicar e corrigir vulnerabilidades.
ini_set('display_errors', 0);
error_reporting(0);
session_start();
$host = 'localhost';
$banco = 'joaom755_db2';
$usuario = 'joaom755';
$senha = '755!@#';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$banco;charset=utf8mb4", $usuario, $senha);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('erro ao conectar o banco de dados.');
}

function limpar($valor)
{
    return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}

$mensagem = $_GET['msg'] ?? '';
$acao = $_POST['acao'] ?? $_GET['acao'] ?? '';
if ($acao === 'login') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['senha'] ?? '';

    $sql = "SELECT * FROM usuarios WHERE email = ?";
    $consulta = $pdo->prepare($sql);
    $consulta->execute([$email]);

    $encontrado = $consulta->fetch(PDO::FETCH_ASSOC);

    if ($encontrado && password_verify($password, $encontrado['senha'])) {

        session_regenerate_id(true);

        $_SESSION['usuario'] = $encontrado['nome'];
        $_SESSION['perfil'] = $encontrado['perfil'];

        $mensagem = 'Bem-vindo, ' . limpar($encontrado['nome']);
    } else {
        $mensagem = 'Login inválido.';
    }
}
if ($acao === 'sair') {
    session_destroy();
    header('Location: index.php?msg=Você saiu');
    exit;
}
if ($acao === 'cadastrar') {
    if (!isset($_SESSION['usuario']) || $_SESSION['perfil'] !== 'admin') {
        die('acesso nao autorizado');
    }
    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $preco = ($_POST['preco'] ?? '');
    $estoque = ($_POST['estoque'] ?? '');
    $categoria = trim($_POST['categoria'] ?? '');

    if ($nome === '' || $categoria === '' || $descricao === '' || $preco === '' || $estoque === '') {
        $mensagem = 'preencha todos os campos';
    } elseif (!is_numeric($preco) || $preco < 0 || !ctype_digit((string) $estoque)) {
        $mensagem = 'preço ou estoque invalido';
    } else {
        $sql = "INSERT INTO produtos(nome, descricao, preco, estoque, categoria) VALUES (?, ?, ?, ?, ?)";
    
    $consulta = $pdo->prepare($sql);
    $consulta->execute([$nome, $descricao, $preco, $estoque, $categoria]);

    $mensagem = 'produto cadastrado com sucesso';
}
}

if ($acao === 'atualizar') {
    if (!isset($_SESSION['usuario']) || $_SESSION['perfil'] !== 'admin') {
        die('acesso nao autorizado');
    }
    $id = ($_POST['id'] ?? '');
    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $preco = ($_POST['preco'] ?? '');
    $estoque = ($_POST['estoque'] ?? '');
    $categoria = trim($_POST['categoria'] ?? '');

    if (!ctype_digit((string)$id) || $id <= 0 || $nome === '' || $categoria === '' || $descricao === '' || $preco === '' || $estoque === '') {
        $mensagem = 'preencha todos os campos';
    } elseif (!is_numeric($preco) || $preco <= 0 || !ctype_digit((string) $estoque)) {
        $mensagem = 'preço ou estoque invalido';
    } else {
        $sql = "UPDATE produtos SET nome=?, descricao=?, preco=?, estoque=?, categoria=? WHERE id=?";

        $consulta = $pdo->prepare($sql);
        $consulta->execute([$nome, $descricao, $preco, $estoque, $categoria]);

        $mensagem = 'produto atualizado com sucesso';
    }
}


if ($acao === 'excluir') {
    if (!isset($_SESSION['usuario']) || $_SESSION['perfil'] !== 'admin') {
        die('acesso nao autorizado');
    }

    $id = $_POST['id'] ?? '';
    if (!ctype_digit((string)$id) && $id > 0) {
        $sql = "DELETE FROM produtos WHERE id = ?";
        $consulta = $pdo->prepare($sql);
        $consulta->execute([$id]);
        $mensagem = 'produto excluido com sucesso';
    } else {
        $mensagem = 'ID inválido';
    }
}
$busca = $_GET['busca'] ?? '';
$categoriaFiltro = $_GET['categoria'] ?? '';
$ordem = $_GET['ordem'] ?? 'id';
$colunas = ['id', 'nome', 'preco'];

if (!in_array($ordem, $colunas, true)) {
    $ordem = 'id';
}

$sqlLista = "SELECT * FROM produtos WHERE (nome LIKE ? OR descricao LIKE ?)";

$valores = ["%$busca%", "%$busca%"];

if ($categoriaFiltro !== '') {
    $sqlLista .= " AND categoria = ?";

    $valores[] = $categoriaFiltro;
}
$sqlLista .= " ORDER BY $ordem";
$consulta = $pdo->prepare($sqlLista);
$consulta->execute([$valores]);

$produtos = $consulta->fetchAll(PDO::FETCH_ASSOC);

$editar = null;
if (isset($_GET['editar'])) {
    if (!isset($_SESSION['usuario']) && $_SESSION['perfil'] !== 'admin' && !ctype_digit((string)$_GET['editar'])) {
        $consulta = $pdo->prepare("SELECT * FROM produtos WHERE id = ?");
        $consulta->execute([$_GET['editar']]);
        $editar = $consulta->fetch(PDO::FETCH_ASSOC);
    }
}
$total = $pdo->query('SELECT COUNT(*) FROM produtos')->fetchColumn();

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estoque Fácil — Laboratório</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <header>
        <h1>Estoque Fácil</h1>
        <p>Sistema de estoque da loja fictícia</p>
    </header>
    <main>
        <div class="aviso">LABORATÓRIO INSEGURO — SOMENTE LOCALHOST</div>
        <?php if ($mensagem !== ''): ?><div class="mensagem"><?= limpar($mensagem) ?></div><?php endif; ?>
        <section class="painel">
            <h2>Acesso ao sistema</h2>
            <?php if (isset($_SESSION['usuario'])): ?>
                <p>Usuário: <?=limpar ($_SESSION['usuario']) ?> | Perfil: <?= limpar($_SESSION['perfil']) ?></p>
                <a href="?acao=sair">Sair</a>
            <?php else: ?>
                <form method="post"><input type="hidden" name="acao" value="login">
                    <label>E-mail <input name="email" placeholder="email@exemplo.local"></label>
                    <label>Senha <input name="senha" type="text" placeholder="Senha"></label>
                    <button>Entrar</button>
                </form>
            <?php endif; ?>
        </section>
        <section class="painel">
            <h2><?= $editar ? 'Editar produto' : 'Cadastrar produto' ?></h2>
            <form method="post" class="grade">
                <input type="hidden" name="acao" value="<?= $editar ? 'atualizar' : 'cadastrar' ?>">
                <?php if ($editar): ?><input type="hidden" name="id" value="<?= $editar['id'] ?>"><?php endif; ?>
                <label>Nome <input name="nome" value="<?= limpar($editar['nome']) ?? '' ?>"></label>
                <label>Categoria <input name="categoria" value="<?= limpar($editar['categoria']) ?? '' ?>"></label>
                <label>Preço (R$) <input name="preco" value="<?= limpar($editar['preco']) ?? '' ?>"></label>
                <label>Estoque <input name="estoque" value="<?= limpar($editar['estoque']) ?? '' ?>"></label>
                <label class="largura">Descrição <textarea name="descricao"><?= limpar($editar['descricao']) ?? '' ?></textarea></label>
                <div><button><?= $editar ? 'Salvar alterações' : 'Cadastrar' ?></button> <a href="index.php">Limpar</a></div>
            </form>
        </section>
        <section class="painel">
            <h2>Produtos cadastrados (<?= limpar($total) ?>)</h2>
            <form method="get" class="filtros">
                <input name="busca" placeholder="Buscar nome ou descrição" value="<?= limpar($busca) ?>">
                <input name="categoria" placeholder="Filtrar categoria" value="<?= limpar($categoriaFiltro) ?>">
                <select name="ordem">
                    <option value="id">Código</option>
                    <option value="nome">Nome</option>
                    <option value="preco">Preço</option>
                    <option value="<?= $ordem ?>" selected>Ordenação atual</option>
                </select>
                <button>Pesquisar</button>
            </form>
            <div class="tabela">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Descrição</th>
                            <th>Categoria</th>
                            <th>Preço</th>
                            <th>Estoque</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produtos as $produto): ?>
                            <tr>
                                <td><?= limpar($produto['id']) ?></td>
                                <td><?= limpar($produto['nome']) ?></td>
                                <td><?= limpar($produto['descricao']) ?></td>
                                <td><?= limpar($produto['categoria']) ?></td>
                                <td>R$ <?= limpar($produto['preco']) ?></td>
                                <td><?= limpar($produto['estoque']) ?></td>
                                <td><a href="?editar=<?= $produto['id'] ?>">Editar</a> <a href="?acao=excluir&id=<?= $produto['id'] ?>">Excluir</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <section class="painel">
            <h2>Observações da loja</h2>
            <p>Esta página foi criada para a turma identificar problemas reais de desenvolvimento web.</p>
            <p>Mensagem de campanha: <?= limpar($_GET['campanha']) ?? 'Nenhuma campanha ativa' ?></p>
        </section>
    </main>
    <footer>Estoque Fácil • Ambiente de laboratório</footer>
</body>

</html>