<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ - Gerenciador de Karaokê</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 20px auto;
            padding: 0 15px;
            background-color: #f4f4f4;
        }
        h1 {
            color: #0056b3;
            text-align: center;
            margin-bottom: 30px;
        }
        .faq-item {
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 15px;
            padding: 15px 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .faq-question {
            font-weight: bold;
            color: #0056b3;
            font-size: 1.1em;
            margin-bottom: 10px;
        }
        .faq-answer {
            color: #555;
        }
    </style>
</head>
<body>

    <h1>Perguntas Frequentes sobre a Ordem das Músicas</h1>
	<p><a href="index.php">&larr; Voltar para o Painel Principal</a></p>

    <div class="faq-item">
        <div class="faq-question">1. Como o sistema decide quem canta primeiro em uma nova rodada?</div>
        <div class="faq-answer">
            <p>O sistema usa uma combinação de critérios para garantir uma distribuição justa. Ele dá prioridade para quem cantou menos vezes no geral e para as mesas e cantores que estão há mais tempo sem cantar. Se todos esses critérios forem iguais, a ordem é definida aleatoriamente.</p>
        </div>
    </div>

    <div class="faq-item">
        <div class="faq-question">2. Minha mesa tem muitas pessoas. Ela terá direito a mais músicas por rodada?</div>
        <div class="faq-answer">
            <p>Sim, o sistema ajusta o número de músicas que uma mesa pode ter em uma rodada com base no seu tamanho. Mesas com mais pessoas têm direito a mais músicas por rodada para tentar acomodar mais cantores.</p>
            <p>Atualmente, mesas com 1 ou 2 pessoas têm direito a 1 música por rodada. Mesas com 3 ou mais pessoas têm direito a 2 músicas por rodada.</p>
        </div>
    </div>

    <div class="faq-item">
        <div class="faq-question">3. Se minha mesa tem direito a mais de uma música na rodada, elas serão tocadas uma após a outra?</div>
        <div class="faq-answer">
            <p>Não diretamente. Mesmo que sua mesa tenha direito a mais de uma música em uma rodada, o sistema prioriza a rotatividade. Após uma música da sua mesa ser tocada, ele verificará se outros cantores ou mesas estão há mais tempo esperando ou cantaram menos, para dar a eles a próxima oportunidade. As músicas da sua mesa serão distribuídas ao longo da rodada, intercaladas com as de outras mesas, garantindo um fluxo justo para todos.</p>
        </div>
    </div>

    <div class="faq-item">
        <div class="faq-question">4. O cantor sempre segue a ordem de sua lista de músicas?</div>
        <div class="faq-answer">
            <p>Sim, um cantor sempre seguirá a ordem de músicas que foi definida na sua lista pessoal. Se a mesma música aparecer mais de uma vez nessa lista, ela será cantada novamente quando chegar a vez dela na sequência.</p>
        </div>
    </div>

    <div class="faq-item">
        <div class="faq-question">5. O que acontece quando um cantor termina todas as músicas de sua lista? Ele vai repetir as músicas do início?</div>
        <div class="faq-answer">
            <p>Não, o cantor não repetirá as músicas do início da sua lista automaticamente. Uma vez que ele tenha cantado todas as músicas que estão cadastradas na ordem da sua lista, ele não será mais selecionado para cantar nas próximas rodadas, a menos que novas músicas sejam adicionadas à sua lista.</p>
        </div>
    </div>

</body>
</html>