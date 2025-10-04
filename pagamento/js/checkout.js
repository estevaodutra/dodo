document.addEventListener("DOMContentLoaded", () => {
  // Elementos do formulário
  const contributionInput = document.getElementById("contribution-input")
  const errorMessage = document.getElementById("error-message")
  const maxErrorMessage = document.getElementById("max-error-message")
  const contributeButton = document.querySelector(".contribute-button")
  const paymentOption = document.querySelector(".payment-option.pix")
  const charityItems = document.querySelectorAll(".charity-item")
  const summaryContribution = document.querySelector(".summary-item:first-child span:last-child")
  const summaryTotal = document.querySelector(".summary-item:last-child span:last-child")

  // Variáveis de controle
  let selectedAmount = 0
  let additionalAmounts = [] // Array para armazenar múltiplos valores adicionais
  const MIN_AMOUNT = 5
  const MAX_AMOUNT = 1000

  // Definir os valores corretos para cada item
  const itemValues = {
    "Vacina Antirrábica": 44.3,
    "1kg de ração": 17.99,
    "Sabonete Matacura": 15.99,
  }

  // Formatar valor como moeda
  function formatCurrency(value) {
    return Number(Number.parseFloat(value).toFixed(2)).toLocaleString("pt-BR", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })
  }

  // Atualizar resumo
  function updateSummary() {
    // Calcular o total somando o valor base e todos os adicionais selecionados
    const additionalTotal = additionalAmounts.reduce((sum, amount) => sum + amount, 0)
    const total = selectedAmount + additionalTotal

    summaryContribution.textContent = `R$ ${formatCurrency(selectedAmount)}`
    summaryTotal.textContent = `R$ ${formatCurrency(total)}`

    // Habilitar/desabilitar botão com base no valor mínimo
    if (total >= MIN_AMOUNT && total <= MAX_AMOUNT) {
      contributeButton.classList.add("active")
      errorMessage.style.display = "none"
      maxErrorMessage.style.display = "none"
    } else if (total > MAX_AMOUNT) {
      contributeButton.classList.remove("active")
      errorMessage.style.display = "none"
      maxErrorMessage.style.display = "block"
    } else {
      contributeButton.classList.remove("active")
      errorMessage.style.display = "block"
      maxErrorMessage.style.display = "none"
    }

    // Log para debug
    console.log("Valores adicionais:", additionalAmounts)
    console.log("Total adicional:", additionalTotal)
    console.log("Total geral:", total)
  }

  // Inicializar máscara de moeda no input
  function initCurrencyMask() {
    contributionInput.addEventListener("input", (e) => {
      let value = e.target.value.replace(/\D/g, "")
      value = (Number.parseInt(value) / 100).toFixed(2)
      e.target.value = formatCurrency(value)

      selectedAmount = Number.parseFloat(value.replace(".", "").replace(",", "."))
      updateSummary()
    })

    // Inicializar com 0,00
    contributionInput.value = formatCurrency(0)
  }

  // Configurar os itens de caridade
  function setupCharityItems() {
    charityItems.forEach((item) => {
      // Obter o nome do item
      const itemName = item.querySelector(".charity-name").textContent

      // Definir o valor correto baseado no nome
      if (itemValues[itemName]) {
        item.dataset.value = itemValues[itemName].toString()
      }

      // Adicionar evento de clique
      item.addEventListener("click", function () {
        const value = Number.parseFloat(this.dataset.value)

        // Toggle seleção
        if (this.classList.contains("selected")) {
          // Remover seleção
          this.classList.remove("selected")

          // Remover valor do array
          additionalAmounts = additionalAmounts.filter(
            (amount) =>
              // Usar toFixed para evitar problemas de precisão com números de ponto flutuante
              Math.abs(amount - value) < 0.001,
          )
        } else {
          // Adicionar seleção
          this.classList.add("selected")

          // Adicionar valor ao array
          additionalAmounts.push(value)
        }

        updateSummary()
      })
    })
  }

  // Inicializar
  function init() {
    // Inicializar máscara de moeda
    initCurrencyMask()

    // Configurar itens de caridade
    setupCharityItems()

    // Marcar PIX como selecionado por padrão
    paymentOption.classList.add("selected")

    // Esconder mensagens de erro inicialmente
    errorMessage.style.display = "none"
    maxErrorMessage.style.display = "none"

    // Atualizar resumo inicial
    updateSummary()
  }

  // Processar pagamento
  contributeButton.addEventListener("click", () => {
    const additionalTotal = additionalAmounts.reduce((sum, amount) => sum + amount, 0)
    const total = selectedAmount + additionalTotal

    // Verificar valor mínimo
    if (total < MIN_AMOUNT) {
      errorMessage.style.display = "block"
      return
    }

    // Verificar valor máximo
    if (total > MAX_AMOUNT) {
      maxErrorMessage.style.display = "block"
      return
    }

    // Mostrar indicador de carregamento no botão
    contributeButton.innerHTML = '<div class="spinner-small"></div> Processando...'
    contributeButton.disabled = true

    // Redirecionar para a página de pagamento com o valor como parâmetro
    window.location.href = `payment.php?amount=${total}`
  })

  // Inicializar tudo
  init()
})
