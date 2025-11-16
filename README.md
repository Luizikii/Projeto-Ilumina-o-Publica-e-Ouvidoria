🏛️ Sistema de Ouvidoria e Iluminação Pública – Prefeitura Municipal de Nepomuceno

Repositório contendo o código-fonte e os arquivos do Trabalho de Conclusão de Curso (TCC) responsáveis pelo desenvolvimento do sistema informatizado para o Setor de Controle Interno e Ouvidoria de Nepomuceno.
O projeto foi implementado com objetivo de modernizar o recebimento, o registro, o acompanhamento e a priorização de demandas de manutenção da iluminação pública.

🧾 Descrição do Projeto

O sistema integra:

✔ Canal WhatsApp para registro de reclamações (via plataforma Menuia).

✔ Aplicação Web em PHP e MySQL para cadastro e gestão interna.

✔ Mapa interativo com Leaflet para exibir reclamações georreferenciadas.

✔ Ordenação por proximidade para otimizar deslocamentos das equipes.

✔ Exportação de relatórios em PDF e Excel.

✔ Página pública para o cidadão acompanhar o status da solicitação.

O software foi projetado para substituir processos em papel e agilizar o fluxo operacional da equipe responsável pela iluminação pública.

🏗️ Arquitetura:

A solução é dividida em três camadas principais:

✔ WhatsApp (Menuia)	Conversação com o cidadão e envio dos dados para o backend

✔ Backend PHP + MySQL	Processamento de regras de negócio e persistência dos registros

✔ Interface Web + Leaflet	Gestão dos cadastros, visualização das ocorrências e consulta pública

📌 Funcionalidades Principais:

Para o cidadão:

✔ Registrar reclamação via WhatsApp

✔ Informar endereço em linguagem natural

✔ Acompanhar status da solicitação em página pública

Para a administração:

✔ Login e controle de acesso

✔ Cadastro de secretarias, setores, tipos de requisição e usuários

✔ Registro manual de novas reclamações

✔ Visualização geográfica das ocorrências (Leaflet)

✔ Ordenação por proximidade

✔ Alteração de status (Pendente → Em andamento → Concluída)

✔ Exportação para PDF e Excel

Tecnologias Utilizadas:

🛠️ PHP 8.x	Backend / Regras de negócio

🛠️ MySQL	Banco de dados

🛠️ JavaScript	Interatividade na interface

🛠️ jQuery	Suporte à UI

🛠️ Bootstrap	Estilização responsiva

🛠️ Leaflet	Mapa interativo

🛠️ Menuia / WhatsApp	Canal conversacional

🛠️ tFPDF	Exportação de PDF

🛠️ PhpSpread sheet	Exportação de Excel
