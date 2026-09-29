import React from 'react';
import Cards from 'react-credit-cards';
import 'react-credit-cards/es/styles-compiled.css';
const settingsRedeCredit = window.wc.wcSettings.getSetting('rede_credit_data', {})
const labelRedeCredit = window.wp.htmlEntities.decodeEntities(settingsRedeCredit.title)
// Obtendo o nonce da variável global
const nonceRedeCredit = settingsRedeCredit.nonceRedeCredit
const translationsRedeCredit = settingsRedeCredit.translations
const minInstallmentsRede = settingsRedeCredit.minInstallmentsRede.replace(',', '.')
const ContentRedeCredit = (props) => {
  const totalAmountFloat = settingsRedeCredit.cartTotal

  const [selectedValue, setSelectedValue] = window.wp.element.useState('')

  const handleSortChange = (event) => {
    setSelectedValue(event.target.value)
    updateCreditObject('rede_credit_installments', event.target.value)
  }

  const { eventRegistration, emitResponse } = props
  const { onPaymentSetup } = eventRegistration
  const wcComponents = window.wc.blocksComponents
  const [creditObject, setCreditObject] = window.wp.element.useState({
    rede_credit_number: '',
    rede_credit_installments: '1',
    rede_credit_expiry: '',
    rede_credit_cvc: '',
    rede_credit_holder_name: ''
  })

  const [focus, setFocus] = window.wp.element.useState('')

  const options = []

  for (let index = 1; index <= settingsRedeCredit.maxInstallmentsRede; index++) {
    if (settingsRedeCredit[`${index}x`] !== undefined) {
      options.push({ key: index, label: settingsRedeCredit[`${index}x`] })
    }
  }

  const formatCreditCardNumber = value => {
    if (value?.length > 24) return creditObject.rede_credit_number
    // Remove caracteres não numéricos
    const cleanedValue = value?.replace(/\D/g, '')
    // Adiciona espaços a cada quatro dígitos
    const formattedValue = cleanedValue?.replace(/(.{4})/g, '$1 ')?.trim()
    return formattedValue
  }

  const onlyDigits = value => String(value == null ? '' : value).replace(/\D/g, '')

  // Validade padronizada: só dígitos, sempre MM/AA (sem espaços). Mês de um dígito
  // 2-9 vira 0X; mês > 12 é limitado a 12; ano com 4 dígitos é cortado para 2
  // ("25/2035" -> "25/35").
  const formatExpiryValue = value => {
    const digits = onlyDigits(value)
    let month = digits.slice(0, 2)
    let year = digits.slice(2)
    if (month.length === 1 && month >= '2' && month <= '9') {
      month = '0' + month
    } else if (month.length === 2 && parseInt(month, 10) > 12) {
      month = '12'
    }
    if (year.length > 2) year = year.slice(-2)
    return year.length ? month + '/' + year : month
  }

  const formatCvcValue = value => onlyDigits(value).slice(0, 4)

  const updateCreditObject = (key, value) => {
    switch (key) {
      case 'rede_credit_expiry':
        setCreditObject({
          ...creditObject,
          [key]: formatExpiryValue(value)
        })
        return
      case 'rede_credit_cvc':
        setCreditObject({
          ...creditObject,
          [key]: formatCvcValue(value)
        })
        return
      default:
        break
    }
    setCreditObject({
      ...creditObject,
      [key]: value
    })
  }

  window.wp.element.useEffect(() => {
    const unsubscribe = onPaymentSetup(async () => {
      // Verifica se todos os campos do creditObject estão preenchidos
      const allFieldsFilled = Object.values(creditObject).every((field) => field.trim() !== '')

      if (allFieldsFilled) {
        return {
          type: emitResponse.responseTypes.SUCCESS,
          meta: {
            paymentMethodData: {
              rede_credit_number: creditObject.rede_credit_number,
              rede_credit_installments: creditObject.rede_credit_installments,
              rede_credit_expiry: creditObject.rede_credit_expiry,
              rede_credit_cvc: creditObject.rede_credit_cvc,
              rede_credit_holder_name: creditObject.rede_credit_holder_name,
              rede_card_nonce: nonceRedeCredit
            }
          }
        }
      }
      return {
        type: emitResponse.responseTypes.ERROR,
        message: translationsRedeCredit.fieldsNotFilled
      }
    })

    // Cancela a inscrição quando este componente é desmontado.
    return () => {
      unsubscribe()
    }
  }, [
    creditObject, // Adiciona creditObject como dependência
    emitResponse.responseTypes.ERROR,
    emitResponse.responseTypes.SUCCESS,
    onPaymentSetup,
    translationsRedeCredit // Adicione translationsRedeCredit como dependência
  ])

  return (
    <>
      <Cards
        number={creditObject.rede_credit_number}
        name={creditObject.rede_credit_holder_name}
        expiry={(creditObject.rede_credit_expiry).replace(/\s+/g, '')}
        cvc={creditObject.rede_credit_cvc}
        placeholders={{
          name: 'NOME', 
          expiry: 'MM/ANO',
          cvc: 'CVC',
          number: '•••• •••• •••• ••••'
        }}
        locale={{ valid: 'VÁLIDO ATÉ' }}
        focused={focus}
      />
      <wcComponents.TextInput
        id="rede_credit_holder_name"
        label={translationsRedeCredit.nameOnCard}
        value={creditObject.rede_credit_holder_name}
        maxLength={30}
        onChange={(value) => {
          updateCreditObject('rede_credit_holder_name', value)
        }}
        onFocus={() => setFocus('name')}
      />

      <wcComponents.TextInput
        id="rede_credit_number"
        label={translationsRedeCredit.cardNumber}
        value={formatCreditCardNumber(creditObject.rede_credit_number)}
        inputMode="numeric"
        onChange={(value) => {
          updateCreditObject('rede_credit_number', formatCreditCardNumber(value))
        }}
        onFocus={() => setFocus('number')}
      />

      <wcComponents.TextInput
        id="rede_credit_expiry"
        label={translationsRedeCredit.cardExpiringDate}
        value={creditObject.rede_credit_expiry}
        inputMode="numeric"
        onChange={(value) => {
          updateCreditObject('rede_credit_expiry', value)
        }}
        onFocus={() => setFocus('expiry')}
      />

      <wcComponents.TextInput
        id="rede_credit_cvc"
        label={translationsRedeCredit.securityCode}
        value={creditObject.rede_credit_cvc}
        inputMode="numeric"
        onChange={(value) => {
          updateCreditObject('rede_credit_cvc', value)
        }}
        onFocus={() => setFocus('cvc')}
      />

      {options.length > 1 && (
        <wcComponents.SortSelect
          instanceId={1}
          className="lknIntegrationRedeForWoocommerceSelectBlocks"
          label={translationsRedeCredit.installments}
          onChange={handleSortChange}
          options={options}
          value={selectedValue}
          readOnly={false}
        />
      )}

    </>
  )
}

const BlockGatewayRedeCredit = {
  name: 'rede_credit',
  label: labelRedeCredit,
  content: window.wp.element.createElement(ContentRedeCredit),
  edit: window.wp.element.createElement(ContentRedeCredit),
  canMakePayment: () => true,
  ariaLabel: labelRedeCredit,
  supports: {
    features: settingsRedeCredit.supports
  }
}

window.wc.wcBlocksRegistry.registerPaymentMethod(BlockGatewayRedeCredit)
