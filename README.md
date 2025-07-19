# 🎤 Fila Karaokê

Este é um sistema de gerenciamento de fila de karaokê simples, desenvolvido em PHP com PDO para interação com o banco de dados MySQL, e jQuery UI para uma interface interativa no frontend.

## 🚀 Funcionalidades

* **Gerenciamento de Cantores:** Adicione e gerencie os cantores participantes a fila.
* **Cadastro de Músicas:** Cadastre músicas com título, artista, código e trecho.
* **Listas de Músicas por Cantor:** Cada cantor pode ter sua própria lista de músicas desejadas.
    * **Pesquisa Inteligente:** Pesquisa de músicas com autocomplete por título, artista, código ou trecho da música.
    * **Reordenação Visual:** Reorganize a ordem das músicas na lista de um cantor via "arrastar e soltar" (drag-and-drop).
* **Gerenciamento da Fila de Rodadas:**
    * Acompanhamento da rodada atual.
    * Exibição da próxima música a ser cantada.
    * Controles para "Finalizar Música" (avançar para a próxima), "Pular Música" e "Trocar Música".
    * Listagem completa da fila da rodada atual.
* **Sistema de Prioridades (por Rodada):** Distribuição de músicas com base em uma lógica de rodadas para garantir que todos cantem.
* **Reinício de Rodadas:** Funcionalidades para resetar o controle de rodadas e a fila.

## ⚙️ Configuração do Ambiente

### Pré-requisitos

* Servidor Web (Apache, Nginx, etc.)
* PHP 7.4+ (ou superior) com extensões PDO e SQLite3 (se for usar SQLite, recomendado para fácil setup)
* Navegador web moderno

### Instalação

1.  **Clone o Repositório:**
    ```bash
    git clone [https://github.com/allighieri/filakaraoke.git](https://github.com/allighieri/filakaraoke.git)
    cd filakaraoke
    ```
2.  **Configuração do Banco de Dados:**
    * Crie um arquivo `config.php` na raiz do projeto (se não existir) com suas configurações de banco de dados.
    * **Exemplo para SQLite (recomendado para simplicidade):**
        ```php
        <?php
        define('DB_DSN', 'sqlite:database.sqlite');
        define('DB_USER', null);
        define('DB_PASS', null);
        
        try {
            $pdo = new PDO(DB_DSN, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // Opcional: Criar o arquivo do banco de dados se não existir
            if (!file_exists('database.sqlite')) {
                // Executar o SQL para criar as tabelas
                $sql = file_get_contents('database.sql');
                $pdo->exec($sql);
                // Inserir dados iniciais de exemplo (opcional)
                $sql_data = file_get_contents('musicas_cantor.sql'); // Se este arquivo tiver INSERTs para musicas e cantores
                $pdo->exec($sql_data);
            }
        } catch (PDOException $e) {
            die("Erro de conexão com o banco de dados: " . $e->getMessage());
        }
        ?>
        ```
    * **Exemplo para MySQL:**
        ```php
        <?php
        define('DB_DSN', 'mysql:host=localhost;dbname=filakaraoke;charset=utf8mb4');
        define('DB_USER', 'seu_usuario');
        define('DB_PASS', 'sua_senha');
        
        try {
            $pdo = new PDO(DB_DSN, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Erro de conexão com o banco de dados: " . $e->getMessage());
        }
        ?>
        ```
3.  **Importar Schema do Banco de Dados:**
    * Execute o conteúdo do arquivo `database.sql` no seu SGBD (phpMyAdmin, DBeaver, linha de comando, etc.) para criar as tabelas necessárias.
    * Opcional: Execute `musicas_cantor.sql` para popular algumas músicas e cantores de exemplo.
4.  **Acessar o Sistema:**
    * Configure seu servidor web para apontar para o diretório `filakaraoke`.
    * Acesse `http://localhost/filakaraoke/index.php` (ou o endereço configurado no seu servidor).

## 📊 Fluxo do Sistema e Regras de Prioridade

O sistema `Fila Karaokê` opera com base em um fluxo de rodadas e prioridades para garantir uma distribuição justa das músicas entre os cantores.

### 1. Cadastro Inicial

* **Cantores:** Primeiramente, cadastre as mesas, ex.: Mesa 01, Mesa 02, Mesa 03. Depois cadastre os cantores atribuindo a eles uma mesa.
* **Músicas:** As músicas disponíveis são cadastradas, incluindo título, artista, código e um campo de "trecho" para facilitar a busca.
* **Músicas por Cantor:** Cada cantor pode ter associada uma lista de músicas que deseja cantar. Essa lista pode ser gerenciada, e as músicas podem ser adicionadas, removidas ou reordenadas conforme a preferência do cantor.

### 2. Montagem da Fila da Rodada

O coração do sistema é a função `montarProximaRodada()` (localizada em `funcoes_fila.php`), que é responsável por preencher a fila da rodada atual seguindo as seguintes regras de prioridade:

1.  **Prioridade 1: Quem cantou há mais tempo:** O sistema verifica qual cantor cantou há mais rodadas (ou nunca cantou). Este cantor terá prioridade para ser incluído na próxima rodada.
2.  **Prioridade 2: Música ainda não cantada na rodada atual:** Dentre as músicas do cantor selecionado (pela prioridade 1), é selecionada uma música que ele ainda não cantou na rodada atual. Isso evita que um cantor cante a mesma música várias vezes consecutivamente se tiver poucas opções.
3.  **Prioridade 3: Ordem de Preferência do Cantor:** Se houver várias músicas que atendam às prioridades anteriores, a música é escolhida com base na `ordem_na_lista` definida pelo próprio cantor na tela de gerenciamento de músicas. A música com menor número de ordem (ou seja, mais "para cima" na lista) terá preferência.

Esta lógica continua até que todos os cantores que possuem músicas em suas listas tenham sido incluídos na rodada, ou até que a rodada atinja um limite pré-definido (se houver).

### 3. Acompanhamento da Rodada

* **Visualização:** Na `index.php`, é exibida a "Rodada Atual", a "Vez da Mesa" (próximo cantor e música), e a "Fila Completa da Rodada".
* **Controles da Música Atual:**
    * **Música Finalizada (Próximo):** Quando uma música é concluída, este botão é clicado. O sistema marca a música como "cantada" para aquele cantor na rodada atual e avança para a próxima música na fila. Se for a última música da rodada, uma nova rodada será iniciada automaticamente na próxima música (ou um aviso será exibido).
    * **Pular Música:** Se um cantor não puder cantar ou quiser pular sua vez, esta opção avança a fila. A música pulada pode ser mantida na lista do cantor para futuras rodadas ou marcada de alguma forma (a implementação detalha o comportamento exato).
    * **Trocar Música:** Permite selecionar outra música para o cantor que está na vez, sem alterar sua posição na fila. Isso é útil se o cantor mudar de ideia sobre a música que deseja cantar no momento.

### 4. Transição de Rodadas

* Quando todas as músicas de uma rodada são finalizadas ou puladas, o sistema detecta o fim da rodada.
* Uma nova rodada é então montada usando novamente a lógica de prioridades (`montarProximaRodada()`), garantindo que os cantores que cantaram há mais tempo (ou que não cantaram na rodada anterior) tenham sua vez.

### 5. Resetar Fila/Rodadas

* Existem funções (`resetarControleRodada`, `resetarFilaRodadas`) para limpar a fila atual e/ou o controle de quem cantou em qual rodada. Isso é útil para reiniciar o karaokê ou corrigir algum problema.

Este sistema visa proporcionar uma experiência de karaokê organizada e justa, dando a todos os participantes a chance de cantar suas músicas preferidas em uma ordem balanceada.

## 🤝 Contribuição

Contribuições são bem-vindas! Sinta-se à vontade para abrir issues ou pull requests.

## 📄 Licença

Este projeto é de código aberto e está sob a licença [MIT License](https://opensource.org/licenses/MIT) (se aplicável, ou outra licença de sua escolha).
